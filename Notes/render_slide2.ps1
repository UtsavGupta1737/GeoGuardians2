# render_slide2.ps1
Add-Type -AssemblyName System.Net
Add-Type -AssemblyName System.Drawing

$port = 9099
$htmlFile = "c:\Users\Yashg\New folder\GeoGuardians2\Notes\slide2_render.html"
$outputImg = "c:\Users\Yashg\New folder\GeoGuardians2\Notes\temp_pptx_inspect\ppt\media\image6.png"
$htmlContent = [System.IO.File]::ReadAllBytes($htmlFile)

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://127.0.0.1:$port/")
$listener.Start()

$edge = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
$tempImg = "c:\Users\Yashg\slide2_capture.png"
if (Test-Path $tempImg) { Remove-Item $tempImg -Force }

$proc = Start-Process -FilePath $edge -ArgumentList "--headless=new", "--screenshot=$tempImg", "--window-size=1920,1080", "--hide-scrollbars", "http://127.0.0.1:$port/" -PassThru

# Serve request
$context = $listener.GetContext()
$response = $context.Response
$response.ContentType = "text/html; charset=utf-8"
$response.ContentLength64 = $htmlContent.Length
$response.OutputStream.Write($htmlContent, 0, $htmlContent.Length)
$response.OutputStream.Close()
$listener.Stop()

$proc.WaitForExit(10000)

if (Test-Path $tempImg) {
    Copy-Item $tempImg $outputImg -Force
    Write-Host "Success: Captured Slide 2 at $outputImg (Size: $((Get-Item $outputImg).Length) bytes)."
    Remove-Item $tempImg -Force
} else {
    Write-Host "Error: Capture failed."
}
