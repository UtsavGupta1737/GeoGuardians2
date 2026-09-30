# update_slide1.ps1
Add-Type -AssemblyName System.IO.Compression.FileSystem

$inspectDir = "c:\Users\Yashg\New folder\GeoGuardians2\Notes\temp_pptx_inspect"
$slide1Path = "$inspectDir\ppt\slides\slide1.xml"
$outputPptx = "c:\Users\Yashg\New folder\GeoGuardians2\Notes\SIH2026-IDEA-Presentation-Format.pptx.pptx"

# Read original slide1.xml
[xml]$xml = Get-Content $slide1Path -Encoding UTF8

$nsManager = New-Object System.Xml.XmlNamespaceManager($xml.NameTable)
$nsManager.AddNamespace("p", "http://schemas.openxmlformats.org/presentationml/2006/main")
$nsManager.AddNamespace("a", "http://schemas.openxmlformats.org/drawingml/2006/main")

# 1. Remove TextBox 12 (the old floating "Blackout Emergency Coordinator")
$tb12 = $xml.SelectSingleNode("//p:sp[p:nvSpPr/p:cNvPr[@name='TextBox 12']]", $nsManager)
if ($tb12) {
    $tb12.ParentNode.RemoveChild($tb12) | Out-Null
    Write-Host "Removed TextBox 12."
}

# 2. Update TextBox 9 txBody content
$tb9 = $xml.SelectSingleNode("//p:sp[p:nvSpPr/p:cNvPr[@name='TextBox 9']]", $nsManager)
if ($tb9) {
    $txBody = $tb9.SelectSingleNode("p:txBody", $nsManager)
    
    # New txBody XML string
    $newTxBodyXml = @'
<p:txBody xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <a:bodyPr anchor="t" rtlCol="false" tIns="0" lIns="0" bIns="0" rIns="0">
    <a:spAutoFit/>
  </a:bodyPr>
  <a:lstStyle/>
  <a:p>
    <a:pPr algn="just" marL="500000" indent="-250000" lvl="1">
      <a:lnSpc><a:spcPts val="6200"/></a:lnSpc>
      <a:buFont typeface="Arial"/>
      <a:buChar char="•"/>
    </a:pPr>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="000000"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Problem Statement ID – </a:t>
    </a:r>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="1F497D"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>SIH26223</a:t>
    </a:r>
  </a:p>
  <a:p>
    <a:pPr algn="just" marL="500000" indent="-250000" lvl="1">
      <a:lnSpc><a:spcPts val="5000"/></a:lnSpc>
      <a:buFont typeface="Arial"/>
      <a:buChar char="•"/>
    </a:pPr>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="000000"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Problem Statement Title – </a:t>
    </a:r>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="2200">
        <a:solidFill><a:srgbClr val="1F497D"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Student Innovation-Disaster management includes ideas related to risk mitigation, Planning and management before, after or during a disaster.</a:t>
    </a:r>
  </a:p>
  <a:p>
    <a:pPr algn="just" marL="500000" indent="-250000" lvl="1">
      <a:lnSpc><a:spcPts val="6200"/></a:lnSpc>
      <a:buFont typeface="Arial"/>
      <a:buChar char="•"/>
    </a:pPr>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="000000"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Theme – </a:t>
    </a:r>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="1F497D"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Disaster Management</a:t>
    </a:r>
  </a:p>
  <a:p>
    <a:pPr algn="just" marL="500000" indent="-250000" lvl="1">
      <a:lnSpc><a:spcPts val="6200"/></a:lnSpc>
      <a:buFont typeface="Arial"/>
      <a:buChar char="•"/>
    </a:pPr>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="000000"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>PS Category – </a:t>
    </a:r>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="1F497D"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Hardware</a:t>
    </a:r>
  </a:p>
  <a:p>
    <a:pPr algn="just" marL="500000" indent="-250000" lvl="1">
      <a:lnSpc><a:spcPts val="6200"/></a:lnSpc>
      <a:buFont typeface="Arial"/>
      <a:buChar char="•"/>
    </a:pPr>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="000000"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Team ID – </a:t>
    </a:r>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="1F497D"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>168129</a:t>
    </a:r>
  </a:p>
  <a:p>
    <a:pPr algn="just" marL="500000" indent="-250000" lvl="1">
      <a:lnSpc><a:spcPts val="6200"/></a:lnSpc>
      <a:buFont typeface="Arial"/>
      <a:buChar char="•"/>
    </a:pPr>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="000000"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>Team Name – </a:t>
    </a:r>
    <a:r>
      <a:rPr lang="en-US" b="true" sz="3000">
        <a:solidFill><a:srgbClr val="1F497D"/></a:solidFill>
        <a:latin typeface="Arial Bold"/>
      </a:rPr>
      <a:t>IILM_GeoGuardians</a:t>
    </a:r>
  </a:p>
</p:txBody>
'@

    $fragDoc = New-Object System.Xml.XmlDocument
    $fragDoc.LoadXml($newTxBodyXml)
    $newNode = $xml.ImportNode($fragDoc.DocumentElement, $true)
    $tb9.ReplaceChild($newNode, $txBody) | Out-Null
    Write-Host "Updated TextBox 9 with new fields."
}

# Save slide1.xml
$xml.Save($slide1Path)
Write-Host "Saved slide1.xml."

# Rebuild PPTX
if (Test-Path $outputPptx) {
    Remove-Item $outputPptx -Force
}
[System.IO.Compression.ZipFile]::CreateFromDirectory($inspectDir, $outputPptx)
Write-Host "Rebuilt PPTX successfully at $outputPptx. Size: $((Get-Item $outputPptx).Length) bytes."
