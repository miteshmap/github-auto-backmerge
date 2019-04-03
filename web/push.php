<?php

use Cz\Git\GitRepository;

$upstream_branches = [
  'master' => 'uat',
  'uat' => 'qa',
  'qa' => 'develop',
];

function webhook_push_callback($payload) {
  // Get the source branch from the payload.
  $branch = str_replace('refs/heads/', '', $payload->ref);

  $repo = init_git_repository();
  $branches = $repo->getBranches();

  error_log('branches');
  error_log(var_export($branches, 1));

  $branches = $repo->getLocalBranches();

  error_log('Local branches');
  error_log(var_export($branches, 1));

  $branches = $repo->getRemoteBranches();

  error_log('Remote branches');
  error_log(var_export($branches, 1));


  foreach ($branches as $branch) {

  }
}

function init_git_repository() {
  $dir = '/tmp/alshaya';
  delete_directory($dir);

  $repo = FALSE;
  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com/vbouchet31/test-php-git.git', $dir);
    $repo->fetch();
  }
  catch (Exception $e) {}

  return $repo;
}

function delete_directory($dir) {
  if (!file_exists($dir)) {
    return true;
  }

  if (!is_dir($dir)) {
    return unlink($dir);
  }

  foreach (scandir($dir) as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }

    if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
      return false;
    }

  }

  return rmdir($dir);
}