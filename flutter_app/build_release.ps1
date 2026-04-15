# build_release.ps1
# TheraPano Release Automation Script

param (
    [switch]$GitHubRelease = $false
)

$ErrorActionPreference = "Stop"

# 1. Configuration
$ISCC_PATH = "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
$SIGNTOOL_PATH = "C:\Program Files (x86)\Windows Kits\10\bin\10.0.19041.0\x64\signtool.exe" # Adjust as needed

# 2. Extract Version from pubspec.yaml
Write-Host "--- Extracting Version ---" -ForegroundColor Cyan
$pubspec = Get-Content "pubspec.yaml" -Raw
$versionMatch = [regex]::Match($pubspec, 'version:\s+([0-9]+\.[0-9]+\.[0-9]+)')
if ($versionMatch.Success) {
    $VERSION = $versionMatch.Groups[1].Value
    Write-Host "Found version: $VERSION"
} else {
    Write-Error "Could not find version in pubspec.yaml"
}

# 3. Create Certificate if missing
Write-Host "--- Handling Certificates ---" -ForegroundColor Cyan
if (-not (Test-Path "therapano_cert.pfx") -or -not (Test-Path "therapano_cert.cer")) {
    Write-Host "Generating self-signed certificate..."
    $cert = New-SelfSignedCertificate -Type Custom -Subject "CN=Therapano" -KeyUsage DigitalSignature -FriendlyName "Therapano Self-Signed" -CertStoreLocation "Cert:\CurrentUser\My" -NotAfter (Get-Date).AddYears(10) -TextExtension @("2.5.29.37={text}1.3.6.1.5.5.7.3.3")
    
    # Export PFX (using a default password for automation)
    $pwd = ConvertTo-SecureString -String "therapano" -Force -AsPlainText
    Export-PfxCertificate -Cert $cert -FilePath "therapano_cert.pfx" -Password $pwd
    
    # Export CER for the installer
    Export-Certificate -Cert $cert -FilePath "therapano_cert.cer"
}

# 4. Build Flutter Windows
Write-Host "--- Building Flutter Windows ---" -ForegroundColor Cyan
flutter build windows --release

# 5. Sign the main executable
Write-Host "--- Signing Executable ---" -ForegroundColor Cyan
if (Test-Path $SIGNTOOL_PATH) {
    & $SIGNTOOL_PATH sign /f "therapano_cert.pfx" /p "therapano" /fd SHA256 /t http://timestamp.digicert.com "build\windows\x64\runner\Release\therapano.exe"
} else {
    Write-Warning "signtool.exe not found at $SIGNTOOL_PATH. Skipping signing."
}

# 6. Run Inno Setup Compiler
Write-Host "--- Compiling Installer ---" -ForegroundColor Cyan
if (Test-Path $ISCC_PATH) {
    & $ISCC_PATH /DAppVersion=$VERSION "therapano_setup.iss"
} else {
    Write-Error "Inno Setup Compiler not found at $ISCC_PATH"
}

# 7. Sign the Setup.exe
$SETUP_EXE = "releases\$VERSION\TherapanoSetup_v$VERSION.exe"
Write-Host "--- Signing Setup.exe ---" -ForegroundColor Cyan
if ((Test-Path $SIGNTOOL_PATH) -and (Test-Path $SETUP_EXE)) {
    & $SIGNTOOL_PATH sign /f "therapano_cert.pfx" /p "therapano" /fd SHA256 /t http://timestamp.digicert.com $SETUP_EXE
}

# 8. Optional: GitHub Release via CLI (gh)
if ($GitHubRelease) {
    Write-Host "--- Creating GitHub Release ---" -ForegroundColor Cyan
    if (Get-Command gh -ErrorAction SilentlyContinue) {
        gh release create "v$VERSION" $SETUP_EXE --title "Release v$VERSION" --notes "Automated release of v$VERSION"
    } else {
        Write-Warning "GitHub CLI (gh) not found. Skipping release creation."
    }
}

Write-Host "--- Build Complete! ---" -ForegroundColor Green
Write-Host "Installer is located at: $SETUP_EXE"
