$path = 'C:\Users\Admin\OneDrive\PHD\BackupDB\CentralDB.mdb'
$conn = New-Object System.Data.Odbc.OdbcConnection("Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$path;")
$conn.Open()
$cmd = $conn.CreateCommand()
$cmd.CommandText = 'SELECT PlaceTypeID, PlaceTypeE, PlaceTypeK FROM PlaceType ORDER BY PlaceTypeID'
$da = New-Object System.Data.Odbc.OdbcDataAdapter($cmd)
$dt = New-Object System.Data.DataTable
[void]$da.Fill($dt)
$dt | Format-Table -AutoSize | Out-String | Write-Output
$conn.Close()
