$path = 'C:\Users\Admin\OneDrive\PHD\BackupDB\CentralDB.mdb'
$conn = New-Object System.Data.Odbc.OdbcConnection("Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$path;")
$conn.Open()

$cmd = $conn.CreateCommand()
$cmd.CommandText = 'SELECT COUNT(*) AS cnt FROM WorkPlace'
$cnt = $cmd.ExecuteScalar()
Write-Output "WorkPlace count: $cnt"

$cmd2 = $conn.CreateCommand()
$cmd2.CommandText = 'SELECT PlaceTypeID, COUNT(*) AS c FROM WorkPlace GROUP BY PlaceTypeID ORDER BY PlaceTypeID'
$r = $cmd2.ExecuteReader()
Write-Output 'PlaceType usage:'
while ($r.Read()) { Write-Output ("  {0} => {1}" -f $r['PlaceTypeID'], $r['c']) }
$r.Close()

$cmd3 = $conn.CreateCommand()
$cmd3.CommandText = 'SELECT TOP 1 * FROM WorkPlace'
$rd = $cmd3.ExecuteReader()
$schema = $rd.GetSchemaTable()
Write-Output 'WorkPlace columns:'
foreach ($row in $schema.Rows) {
    Write-Output ("  {0} ({1})" -f $row.ColumnName, $row.DataTypeName)
}
$rd.Close()

$conn.Close()
