# Snapshot Plugin

Create and restore a snapshot of your data.

Snapshot contains 
* a database backup
* all your assets (local volumes only)

Tip: Name your directory according to the corresponding
release tag in git.

## CLI commands

* craft snapshot/snapshot/create [directory]
* craft snapshot/snapshot/restore <directory>

## Known issues

* Handle of volumes has to match the folder name,
e.g. Handle 'images' => Folder '@webroot/images'
