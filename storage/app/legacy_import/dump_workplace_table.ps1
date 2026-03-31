$rows = Get-Content storage\app\legacy_import\workplace_old.json -Raw | ConvertFrom-Json
$rows = $rows | Sort-Object WorkPlaceID
$rows | Select-Object WorkPlaceID,PlaceTypeID,WorkPlaceE,WorkPlaceK,Root | Format-Table -AutoSize | Out-String -Width 400 | Set-Content storage\app\legacy_import\workplace_old_table.txt -Encoding UTF8
Write-Output 'saved workplace_old_table.txt'
