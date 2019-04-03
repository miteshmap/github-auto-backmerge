<?php

use Cz\Git\GitRepository;

global $upstream_branches;
$upstream_branches = [
  'master' => 'uat',
  'uat' => 'qa',
  'qa' => 'develop',
];

function webhook_push_callback($payload) {
  global $upstream_branches;

  // Get the source branch from the payload.
  $ref = str_replace('refs/heads/', '', $payload->ref);

  // Identify the default target branch if any.
  $target_branches = isset($upstream_branches[$ref]) ? [$upstream_branches[$ref]] : [];

  $repo = init_git_repository();

  $branches = $repo->getRemoteBranches();

  error_log('Remote branches');
  error_log(var_export($branches, 1));

  array_walk($branches, function (&$branch) {
    $branch = str_replace('origin/HEAD -> ', '', $branch);
    $branch = str_replace('origin/', '', $branch);
  });

  error_log(var_export($branches, 1));

  foreach ($branches as $branch) {
    if ($ref != $branch && substr($branch, 0, strlen($ref)) == $ref) {
      $target_branches[] = $branch;
    }
  }

  error_log(var_export($target_branches));
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