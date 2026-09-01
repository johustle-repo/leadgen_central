# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Controllers/**/*Controller.php,app/Services/**/*.php | .ai/rules/controllers-services.md |
| app/Jobs/ProcessUploadBatch.php|composer.json | .ai/rules/jobs.md |
| app/{Services,Jobs,Http/Controllers}/**/*Gmail*.php,app/Models/EmailReply.php | .ai/rules/models.md |
| app/{Http/Controllers,Http/Requests,Models}/**/*LeadAttachment*.php | .ai/rules/requests-models.md |
| app/{Services,Console/Commands,Models,Http/Controllers}/**/*EmailSequence*.php,app/Services/GmailReplySynchronizer.php | .ai/rules/services.md |
| tests/** | .ai/rules/tests.md |
