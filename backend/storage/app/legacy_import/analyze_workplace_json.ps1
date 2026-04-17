$rows = Get-Content storage\app\legacy_import\workplace_old.json -Raw | ConvertFrom-Json
Write-Output ("rows: {0}" -f $rows.Count)
$rows | Group-Object PlaceTypeID | Sort-Object Name | ForEach-Object { Write-Output ("type {0}: {1}" -f $_.Name, $_.Count) }

Write-Output "\nDepth distribution (segments in WorkPlaceID):"
$rows | Group-Object { ($_.WorkPlaceID -split '\|').Count } | Sort-Object Name | ForEach-Object { Write-Output ("depth {0}: {1}" -f $_.Name, $_.Count) }

Write-Output "\nSample deepest rows:"
$rows | Sort-Object { ($_.WorkPlaceID -split '\|').Count } -Descending | Select-Object -First 20 WorkPlaceID,PlaceTypeID,WorkPlaceE,WorkPlaceK | Format-Table -AutoSize | Out-String | Write-Output
