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

  // Get all the remote branches so we can identify the ones to back-merge to.
  $branches = $repo->getRemoteBranches();

  // Sanitize and normalize branches naming.
  array_walk($branches, function (&$branch) {
    $branch = str_replace('origin/HEAD -> ', '', $branch);
    $branch = str_replace('origin/', '', $branch);
  });

  foreach ($branches as $branch) {
    if ($ref != $branch && substr($branch, 0, strlen($ref)) == $ref) {
      $target_branches[] = $branch;
    }
  }

  foreach ($target_branches as $branch) {
    // @TODO: Detect failure.
    $repo->execute(['reset', '--hard', 'origin/' . $branch]);

    // @TODO: Detect conflicts.
    $str = $repo->execute(['rebase', 'origin/' . $ref]);
    error_log(var_export($str, 1));

    try {
      $repo->push('origin', [$branch]);
    }
    catch (Exception $e) {
      error_log(var_export($e, 1));
    }

  }

  //error_log('We will try to backmerge to following branches:');
  //error_log(var_export($target_branches, 1));
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