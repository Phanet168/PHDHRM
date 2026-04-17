$path = 'C:\Users\Admin\OneDrive\PHD\BackupDB\CentralDB.mdb'
$conn = New-Object System.Data.Odbc.OdbcConnection("Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$path;")
$conn.Open()
$tables = @('PlaceType','WorkPlace','OpDistrict')
foreach ($t in $tables) {
  Write-Output "=== TABLE: $t ==="
  $schema = $conn.GetSchema('Columns', @($null,$null,$t,$null))
  $schema | Select-Object COLUMN_NAME, TYPE_NAME | Format-Table -AutoSize | Out-String | Write-Output
  $cmd = $conn.CreateCommand()
  $cmd.CommandText = "SELECT TOP 8 * FROM [$t]"
  $da = New-Object System.Data.Odbc.OdbcDataAdapter($cmd)
  $dt = New-Object System.Data.DataTable
  [void]$da.Fill($dt)
  $dt | Format-Table -AutoSize | Out-String | Write-Output
}
$conn.Close()
