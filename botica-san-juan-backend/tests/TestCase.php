<?php

namespace Tests;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // La limitacion de intentos se apoya en la cache. En pruebas, phpunit.xml
    // fija CACHE_STORE=array, por lo que cada caso arranca con la cache vacia
    // y no hace falta limpiarla manualmente.

    /**
     * Crea un usuario administrador listo para autenticarse.
     */
    protected function crearAdministrador(array $atributos = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Administrador de Prueba',
            'email' => 'admin.prueba@boticasanjuan.pe',
            'password' => 'Clave#Segura2026',
            'dni' => '10000001',
            'telefono' => '987000001',
            'rol' => 'administrador',
            'mfa_enabled' => false,
        ], $atributos));
    }

    /**
     * Crea un usuario con rol cliente.
     */
    protected function crearCliente(array $atributos = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Cliente de Prueba',
            'email' => 'cliente.prueba@boticasanjuan.pe',
            'password' => 'Clave#Segura2026',
            'dni' => '20000002',
            'telefono' => '987000002',
            'rol' => 'cliente',
            'mfa_enabled' => false,
        ], $atributos));
    }
}
