<?php

namespace App\Support\Imagen;

use GdImage;
use RuntimeException;

/**
 * Convierte la foto subida en un data URI pequeño, apto para una columna TEXT.
 *
 * El tope se aplica al data URI y no al binario: lo que viaja por la red y
 * ocupa en Neon es el base64, un tercio más grande.
 */
class OptimizadorAvatar
{
    public const LADO_MAXIMO = 256;

    /** Estrictamente por debajo de 50 KB. */
    public const BYTES_MAXIMOS = 50 * 1024 - 1;

    /** @throws RuntimeException si el contenido no se puede decodificar. */
    public function aDataUri(string $ruta): string
    {
        $original = @imagecreatefromstring((string) file_get_contents($ruta));

        if ($original === false) {
            throw new RuntimeException('La imagen no se puede decodificar.');
        }

        /* Se gira DESPUÉS de reducir: girar 16 megapíxeles duplica la memoria
           y tumba el proceso con el límite por omisión de PHP. */
        $cuadrada = $this->enderezar($this->recortar($original, self::LADO_MAXIMO), $ruta);

        foreach ([self::LADO_MAXIMO, 128, 64] as $lado) {
            $lienzo = imagesx($cuadrada) > $lado ? $this->recortar($cuadrada, $lado) : $cuadrada;

            foreach ([82, 70, 55, 40] as $calidad) {
                $uri = $this->codificar($lienzo, $calidad);

                if (strlen($uri) <= self::BYTES_MAXIMOS) {
                    return $uri;
                }
            }
        }

        throw new RuntimeException('La imagen no se pudo reducir lo suficiente.');
    }

    /** Recorte cuadrado centrado: el avatar se muestra siempre en círculo. */
    private function recortar(GdImage $origen, int $maximo): GdImage
    {
        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = min($ancho, $alto);
        $destino = min($lado, $maximo);

        $lienzo = imagecreatetruecolor($destino, $destino);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);

        imagecopyresampled(
            $lienzo, $origen, 0, 0,
            intdiv($ancho - $lado, 2), intdiv($alto - $lado, 2),
            $destino, $destino, $lado, $lado,
        );

        return $lienzo;
    }

    /**
     * Recodificar descarta el EXIF, y con él la orientación que el navegador
     * aplicaba: sin esto las fotos de móvil saldrían tumbadas.
     */
    private function enderezar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data') || @exif_imagetype($ruta) !== IMAGETYPE_JPEG) {
            return $imagen;
        }

        /* `imagerotate` gira en sentido antihorario. Las variantes espejadas
           (2, 4, 5 y 7) no las produce ninguna cámara y se dejan como están. */
        $grados = match ((int) ((@exif_read_data($ruta) ?: [])['Orientation'] ?? 1)) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        return $grados === 0 ? $imagen : (imagerotate($imagen, $grados, 0) ?: $imagen);
    }

    private function codificar(GdImage $imagen, int $calidad): string
    {
        $webp = function_exists('imagewebp');

        ob_start();
        $webp ? imagewebp($imagen, null, $calidad) : imagejpeg($this->sobreBlanco($imagen), null, $calidad);
        $binario = (string) ob_get_clean();

        if ($binario === '') {
            throw new RuntimeException('No se pudo codificar la imagen.');
        }

        return 'data:image/'.($webp ? 'webp' : 'jpeg').';base64,'.base64_encode($binario);
    }

    /** JPEG no tiene transparencia: sin fondo, un PNG recortado saldría negro. */
    private function sobreBlanco(GdImage $imagen): GdImage
    {
        $lienzo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopy($lienzo, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));

        return $lienzo;
    }
}
