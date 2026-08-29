<#
.SYNOPSIS
    Captura la ventana del navegador y la guarda como PNG.

.DESCRIPTION
    Utilitario para generar evidencias del sistema en ejecucion para los
    informes del curso. Navega a una URL, trae la ventana del navegador al
    frente y captura unicamente esa ventana, evitando que otras aplicaciones
    aparezcan en la evidencia.

.EXAMPLE
    pwsh -File .\infra\evidencias\capturar_pantalla.ps1 -Url "http://127.0.0.1:5173/" -Nombre "01_portada"
#>
[CmdletBinding()]
param(
    [string]$Url,
    [Parameter(Mandatory = $true)][string]$Nombre,
    [int]$EsperaSeg = 9,
    [string]$Destino = "$PSScriptRoot\capturas"
)

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

if (-not ("Win32Ventana" -as [type])) {
    Add-Type @"
using System;
using System.Runtime.InteropServices;
public class Win32Ventana {
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr hWnd);
    [DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr hWnd, out RECT lpRect);
    [StructLayout(LayoutKind.Sequential)]
    public struct RECT { public int Left, Top, Right, Bottom; }
}
"@
}

New-Item -ItemType Directory -Force -Path $Destino | Out-Null

function Obtener-VentanaEdge {
    Get-Process msedge -ErrorAction SilentlyContinue |
        Where-Object { $_.MainWindowHandle -ne 0 } |
        Sort-Object StartTime -Descending |
        Select-Object -First 1
}

$proceso = Obtener-VentanaEdge

if ($Url) {
    if ($proceso) {
        # Reutiliza la ventana abierta: nueva pestana con la URL solicitada
        Start-Process "msedge.exe" -ArgumentList $Url
    } else {
        Start-Process "msedge.exe" -ArgumentList "--start-maximized", "--new-window", $Url
        Start-Sleep -Seconds 6
        $proceso = Obtener-VentanaEdge
    }
    Start-Sleep -Seconds $EsperaSeg
    $proceso = Obtener-VentanaEdge
}

if (-not $proceso) { throw "No se encontro una ventana de Microsoft Edge abierta." }

# Maximizar y traer al frente
[Win32Ventana]::ShowWindow($proceso.MainWindowHandle, 3) | Out-Null
[Win32Ventana]::SetForegroundWindow($proceso.MainWindowHandle) | Out-Null
Start-Sleep -Milliseconds 1400

$rect = New-Object Win32Ventana+RECT
[Win32Ventana]::GetWindowRect($proceso.MainWindowHandle, [ref]$rect) | Out-Null

$ancho = $rect.Right - $rect.Left
$alto  = $rect.Bottom - $rect.Top

if ($ancho -le 0 -or $alto -le 0) {
    $b = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
    $rect.Left = $b.Left; $rect.Top = $b.Top; $ancho = $b.Width; $alto = $b.Height
}

$bmp = New-Object System.Drawing.Bitmap($ancho, $alto)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.CopyFromScreen($rect.Left, $rect.Top, 0, 0, (New-Object System.Drawing.Size($ancho, $alto)))

$ruta = Join-Path $Destino "$Nombre.png"
$bmp.Save($ruta, [System.Drawing.Imaging.ImageFormat]::Png)

$g.Dispose(); $bmp.Dispose()

Write-Host "Captura guardada: $Nombre.png  ($ancho x $alto)"
