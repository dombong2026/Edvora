# Encrypted staging backups and independent restore rehearsal

Status: scripts implemented; NO aaPanel backup or restore executed.

## Required operator setup

- A dedicated QA schema named edvora_qa_<suffix> or edvora_staging_<suffix>.
- MySQL client defaults file with mode 600, not stored in Git.
- AGE public encryption recipient and separately protected private identity file.
- A private pre-created backup folder /var/backups/edvora-staging, mode 700.
- A separate empty schema edvora_restore_<suffix> with restricted credentials.
- A documented authorization, operator, incident/reference ID and planned QA window.

## Backup example

Set AGE_RECIPIENT in the trusted operator environment, then run:

    bash support/qa/staging-backup-encrypted.sh \
      edvora_staging_acceptance /root/private/staging-dump.cnf \
      /var/backups/edvora-staging

The script refuses other database prefixes, checks private file and directory
permissions, and encrypts SQL to the AGE recipient while streaming, without
creating a plaintext disk dump. It produces an encrypted archive and SHA-256
digest. Secure storage and ciphertext backup replication remain operator tasks.

## Disposable restore example

Set EDVORA_RESTORE_ACK=DISPOSABLE_STAGING_DATABASE_ONLY, then execute:

    bash support/qa/staging-restore-disposable.sh \
      /var/backups/edvora-staging/<verified-archive>.sql.age \
      edvora_restore_acceptance /root/private/restore.cnf \
      /root/private/age-identity.txt

This command DOES write into the named disposable schema. It rejects nonempty
restoration databases and unsafe path/name formats; it never targets production
names. The database restore user should have access to only the disposable
schema, not to source/production schemas.

## Independent recovery acceptance

After restoration, independently test table counts, migration version, data
integrity, institution foreign keys, role isolation, synthetic account login,
encrypted columns and any approved staging private media. Record restoration
duration and reconcile recovered data with the approved snapshot.

Full RPO/RTO analysis, offsite backups, regular restore drills, ransomware
resilience, private media recovery, key rotation, and production incident
response need independent security review. These scripts are not evidence
that an actual restore has passed.
