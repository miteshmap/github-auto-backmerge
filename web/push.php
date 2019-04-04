<?php

use Cz\Git\GitRepository;
use \Cz\Git\GitException;

global $upstream_branches;
$upstream_branches = [
  'master' => 'uat',
  'uat' => 'qa',
  'qa' => 'develop',
];

function webhook_push_callback($payload) {
  $dir = '/tmp/' . uniqid('alshaya-');

  // Be sure the target directory does not exist yet.
  delete_directory($dir);

  // Clone the repository into the target directory.
  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com/vbouchet31/test-php-git.git', $dir);
    $repo->fetch();
  }
  catch (Exception $e) {
    error_log('Impossible to clone repository into ' . $dir . '.');
    return;
  }

  global $upstream_branches;

  // Get the source branch from the payload.
  $ref = str_replace('refs/heads/', '', $payload->ref);

  // Identify the default target branch if any.
  $target_branches = isset($upstream_branches[$ref]) ? [$upstream_branches[$ref]] : [];

  // Get all the remote branches so we can identify the ones to back merge to.
  $branches = $repo->getRemoteBranches();

  // Sanitize and normalize branches naming.
  array_walk($branches, function (&$branch) {
    $branch = str_replace('origin/HEAD -> ', '', $branch);
    $branch = str_replace('origin/', '', $branch);
  });

  // Find feature branches based on current branch.
  foreach ($branches as $branch) {
    if ($ref != $branch && substr($branch, 0, strlen($ref)) == $ref) {
      $target_branches[] = $branch;
    }
  }

  // Stop the process if there no target branch to back merge to.
  if (empty ($target_branches)) {
    error_log($ref . ' does not need to be back merged into any branch.');
    return;
  }

  // Print all the branches we should back merge to.
  error_log('We will merge ' . $ref . ' change into following branches: ' . implode(', ', $target_branches) . '.');

  // Browse all the target branches to back merge and push to the repository.
  foreach ($target_branches as $branch) {
    error_log('Back-merging ' . $ref . ' into ' . $branch . '.');

    // Checkout the target branch.
    try {
      error_log('Checkout branch ' . $branch);
      $repo->checkout($branch);
    }
    catch (GitException $e) {
      error_log('Impossible to checkout branch ' .$branch . '.');
      error_log($e->getMessage());
      continue;
    }


    // Hard reset the repo to the target branch so directory is clean.
    // @TODO: Detect failure (is it even possible ?).
    try {
      error_log('Reset branch ' . $branch . '.');
      $repo->execute(['reset', '--hard', 'origin/' . $branch]);
    }
    catch (GitException $e) {
      error_log('Impossible to hard reset branch ' . $branch . '.');
      error_log($e->getMessage());
      continue;
    }

    // Pull the parent branch into the target branch.
    // @TODO: Investigate the true difference with rebase.
    try {
      //$repo->pull('origin', [$ref]);
      $str = $repo->execute(['pull', 'origin', $ref]);
      error_log(var_export($str, 1));
    }
    catch (GitException $e) {
      // @TODO: Notify about the conflicts.
      error_log('Impossible to pull ' . $ref . ' into ' . $branch);
      error_log($e->getMessage());
      continue;
    }

    try {
      $str = $repo->execute(['push', 'origin', $branch]);
      error_log(var_export($str, 1));
    }
    catch (GitException $e) {
      // @TODO: Notify about the error. Concurrent merges?
      error_log('Impossible to push into ' . $branch);
      error_log($e->getMessage());
      continue;
    }
  }

  delete_directory($dir);
}

/**
 * Recursive function delete a directory (and sub-directories).
 */
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