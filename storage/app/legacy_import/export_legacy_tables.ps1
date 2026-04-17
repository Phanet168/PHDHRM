param(
    [string]$MdbPath = 'C:\Program Files (x86)\Ministry Of Health\DMT\CentralDB.mdb',
    [string]$OutputDir = 'storage\app\legacy_import'
)

if (-not (Test-Path -Path $MdbPath -PathType Leaf)) {
    throw "MDB file not found: $MdbPath"
}

if (-not (Test-Path -Path $OutputDir -PathType Container)) {
    New-Item -Path $OutputDir -ItemType Directory -Force | Out-Null
}

$tableMap = [ordered]@{
    'WorkPlace'     = 'workplace_old.json'
    'PlaceType'     = 'place_type.json'
    'Province'      = 'province.json'
    'OpDistrict'    = 'op_district.json'
    'Skill'         = 'skill.json'
    'Position'      = 'position.json'
    'PayLevel'      = 'pay_level.json'
    'SSLType'       = 'ssl_type.json'
    'SSLValues'     = 'ssl_values.json'
    'Staff'         = 'staff.json'
    'WorkHistory'   = 'work_history.json'
    'StatusHistory' = 'status_history.json'
    'WorkStatus'    = 'work_status.json'
    'MaritalStatus' = 'marital_status.json'
    'TblCadre'      = 'tbl_cadre.json'
}

$conn = New-Object System.Data.Odbc.OdbcConnection("Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$MdbPath;")
$conn.Open()

try {
    $exportReport = @()
    foreach ($table in $tableMap.Keys) {
        $cmd = $conn.CreateCommand()
        $cmd.CommandText = "SELECT * FROM [$table]"
        $da = New-Object System.Data.Odbc.OdbcDataAdapter($cmd)
        $dt = New-Object System.Data.DataTable
        [void]$da.Fill($dt)

        $rows = @()
        foreach ($r in $dt.Rows) {
            $obj = [ordered]@{}
            foreach ($c in $dt.Columns) {
                $name = [string]$c.ColumnName
                $value = $r[$name]
                if ($value -eq [DBNull]::Value) {
                    $obj[$name] = $null
                } else {
                    $obj[$name] = $value
                }
            }
            $rows += [pscustomobject]$obj
        }

        $filename = $tableMap[$table]
        $outPath = Join-Path $OutputDir $filename
        $rows | ConvertTo-Json -Depth 8 | Set-Content -Path $outPath -Encoding UTF8

        $exportReport += [pscustomobject]@{
            Table = $table
            Rows = $rows.Count
            File = $filename
        }
    }

    $reportPath = Join-Path $OutputDir 'legacy_export_report.txt'
    $exportReport | Sort-Object Table | Format-Table -AutoSize | Out-String | Set-Content -Path $reportPath -Encoding UTF8
    $exportReport | Sort-Object Table | Format-Table -AutoSize
    Write-Output "Report: $reportPath"
}
finally {
    if ($conn.State -eq 'Open') {
        $conn.Close()
    }
}
