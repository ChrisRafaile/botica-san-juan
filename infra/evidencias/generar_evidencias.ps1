<#
.SYNOPSIS
    Genera las capturas de evidencia del sistema en ejecucion.

.DESCRIPTION
    Abre cada pantalla del sistema en una ventana aislada de Microsoft Edge
    (perfil temporal propio, modo aplicacion sin barra de pestanas) y captura
    unicamente esa ventana. Al usar un perfil dedicado se evita que pestanas o
    ventanas personales del usuario aparezcan en la evidencia.

.EXAMPLE
    pwsh -File .\infra\evidencias\generar_evidencias.ps1
#>
[CmdletBinding()]
param(
    [string]$BaseUrl  = "http://127.0.0.1:5173",
    [string]$Destino  = "$PSScriptRoot\capturas",
    [string]$PerfilTmp = "$env:TEMP\edge_evidencias_botica",
    [int]$Ancho = 1600,
    [int]$Alto  = 1000
)

Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName System.Windows.Forms

if (-not ("Win32Ev" -as [type])) {
    Add-Type @"
using System;
using System.Runtime.InteropServices;
public class Win32Ev {
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr h);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [StructLayout(LayoutKind.Sequential)]
    public struct RECT { public int Left, Top, Right, Bottom; }
}
"@
}

New-Item -ItemType Directory -Force -Path $Destino | Out-Null

# Pantallas a capturar: nombre de archivo, ruta, segundos de espera
$pantallas = @(
    @{ n = "sis_01_portal_publico"; url = "/";                    espera = 11 },
    @{ n = "sis_02_catalogo";       url = "/products";            espera = 11 },
    @{ n = "sis_03_login";          url = "/login";               espera = 9  },
    @{ n = "sis_04_servicios";      url = "/services";            espera = 9  },
    @{ n = "sis_05_cobertura";      url = "/coverage";            espera = 10 }
)

function Capturar-Ventana($proceso, $rutaSalida) {
    [Win32Ev]::SetForegroundWindow($proceso.MainWindowHandle) | Out-Null
    Start-Sleep -Milliseconds 1200

    $rect = New-Object Win32Ev+RECT
    [Win32Ev]::GetWindowRect($proceso.MainWindowHandle, [ref]$rect) | Out-Null

    $w = $rect.Right - $rect.Left
    $h = $rect.Bottom - $rect.Top
    if ($w -le 0 -or $h -le 0) { throw "No se pudo medir la ventana." }

    $bmp = New-Object System.Drawing.Bitmap($w, $h)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.CopyFromScreen($rect.Left, $rect.Top, 0, 0, (New-Object System.Drawing.Size($w, $h)))
    $bmp.Save($rutaSalida, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose(); $bmp.Dispose()
    return "$w x $h"
}

foreach ($p in $pantallas) {
    $url = "$BaseUrl$($p.url)"

    # Ventana aislada en modo aplicacion: sin pestanas, sin barra de favoritos
    $args = @(
        "--user-data-dir=$PerfilTmp",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-features=Translate,PrivacySandboxSettings4",
        "--window-size=$Ancho,$Alto",
        "--window-position=0,0",
        "--app=$url"
    )

    $proc = Start-Process "msedge.exe" -ArgumentList $args -PassThru
    Start-Sleep -Seconds $p.espera

    # La ventana visible puede pertenecer a un proceso hijo de Edge
    $ventana = Get-Process msedge -ErrorAction SilentlyContinue |
               Where-Object { $_.MainWindowHandle -ne 0 } |
               Sort-Object StartTime -Descending | Select-Object -First 1

    if ($ventana) {
        $ruta = Join-Path $Destino "$($p.n).png"
        $tam = Capturar-Ventana $ventana $ruta
        Write-Host "OK  $($p.n).png  ($tam)  <- $url"
    } else {
        Write-Warning "No se encontro la ventana para $url"
    }

    Get-Process msedge -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -and $_.StartTime -gt (Get-Date).AddSeconds(-60) } |
        Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
}

Write-Host ""
Write-Host "Capturas generadas en: $Destino"
Get-ChildItem $Destino -Filter *.png | Select-Object Name, @{n='KB';e={[math]::Round($_.Length/1KB)}}
