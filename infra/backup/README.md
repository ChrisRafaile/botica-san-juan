# Backup and Restore (SQLite Example)

## Backup

```powershell
pwsh -File .\infra\backup\backup_sqlite.ps1
```

## Restore

```powershell
pwsh -File .\infra\backup\restore_sqlite.ps1 -BackupFile .\infra\backup\database.20260101-120000.sqlite
```

## Restore drill con evidencia

```powershell
pwsh -File .\infra\backup\run_restore_drill.ps1 -Environment staging
```

El script genera evidencia en `botica-san-juan-backend/logs/backup-evidence/<timestamp>/summary.json`.

## Recommended policy

1. Run backup daily.
2. Keep at least 7 daily backups and 4 weekly backups.
3. Validate restore at least once per month in staging.
4. Store checksum evidence (SHA256) per backup.
