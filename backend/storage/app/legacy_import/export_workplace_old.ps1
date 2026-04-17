$path = 'C:\Users\Admin\OneDrive\PHD\BackupDB\CentralDB.mdb'
$conn = New-Object System.Data.Odbc.OdbcConnection("Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$path;")
$conn.Open()
$cmd = $conn.CreateCommand()
$cmd.CommandText = 'SELECT WorkPlaceID, WorkPlaceE, WorkPlaceK, PlaceTypeID, ProvinceID, OpDistrictID, Root FROM WorkPlace ORDER BY WorkPlaceID'
$da = New-Object System.Data.Odbc.OdbcDataAdapter($cmd)
$dt = New-Object System.Data.DataTable
[void]$da.Fill($dt)
$rows = @()
foreach($r in $dt.Rows){
  $rows += [pscustomobject]@{
    WorkPlaceID = if ($r.WorkPlaceID -eq [DBNull]::Value) { $null } else { [string]$r.WorkPlaceID }
    WorkPlaceE = if ($r.WorkPlaceE -eq [DBNull]::Value) { $null } else { [string]$r.WorkPlaceE }
    WorkPlaceK = if ($r.WorkPlaceK -eq [DBNull]::Value) { $null } else { [string]$r.WorkPlaceK }
    PlaceTypeID = if ($r.PlaceTypeID -eq [DBNull]::Value) { $null } else { [int]$r.PlaceTypeID }
    ProvinceID = if ($r.ProvinceID -eq [DBNull]::Value) { $null } else { [int]$r.ProvinceID }
    OpDistrictID = if ($r.OpDistrictID -eq [DBNull]::Value) { $null } else { [int]$r.OpDistrictID }
    Root = if ($r.Root -eq [DBNull]::Value) { $false } else { [bool]$r.Root }
  }
}
$rows | ConvertTo-Json -Depth 4 | Set-Content -Path 'storage\app\legacy_import\workplace_old.json' -Encoding UTF8
Write-Output "exported: $($rows.Count) rows"
$conn.Close()
