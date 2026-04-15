; -- therapano_setup.iss --
; Inno Setup Script for TheraPano Windows Application

#define MyAppName "Therapano"
#define MyAppPublisher "Therapano"
#define MyAppURL "https://therapano.de"
#define MyAppExeName "therapano.exe"
#define MyAppIconName "assets\icons\therapano_icon.ico"

; Version will be passed as a command line parameter from the build script
#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif

[Setup]
AppId={{91D229D0-0E93-4193-A8F8-ED1F1122D24B}
AppName={#MyAppName}
AppVersion={#AppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\{#MyAppName}
DisableProgramGroupPage=yes
; Artifacts directory
OutputDir=releases\{#AppVersion}
OutputBaseFilename=TherapanoSetup_v{#AppVersion}
SetupIconFile={#MyAppIconName}
Compression=lzma
SolidCompression=yes
WizardStyle=modern
; Requires admin for certificate installation
PrivilegesRequired=admin

[Languages]
Name: "german"; MessagesFile: "compiler:Languages\German.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked

[Files]
; The build output from flutter build windows --release
Source: "build\windows\x64\runner\Release\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
; The certificate to be installed
Source: "therapano_cert.cer"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{autoprograms}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
; Install the self-signed certificate silently
Filename: "certutil.exe"; Parameters: "-addstore ""Root"" ""{app}\therapano_cert.cer"""; Flags: runhidden
; Start the app after installation
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[UninstallRun]
; Remove the certificate during uninstallation
Filename: "certutil.exe"; Parameters: "-delstore ""Root"" ""Therapano"""; Flags: runhidden
