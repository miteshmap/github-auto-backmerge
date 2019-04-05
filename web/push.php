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
    $repo->execute(['config', '--local', 'user.name', 'alshaya-github-bot']);
    $repo->execute(['config', '--local', 'user.email', 'vincent.bouchet+alshaya-github-bot@acquia.com']);
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
  foreach (array_reverse($target_branches) as $branch) {
    error_log(NULL);
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
      error_log('Pull branch ' . $ref . ' into ' . $branch);
      $repo->pull('origin', [$ref]);
    }
    catch (GitException $e) {
      error_log('Impossible to pull ' . $ref . ' into ' . $branch);

      // Get the list of conflicting files.
      $files = $repo->execute(['diff', '--name-only', '--diff-filter=U']);
      error_log(var_export($files, 1));

      // Prepare and send notification to Slack.
      // @TODO: Add github username from $payload.
      // @TODO: Add a link to the diff on github.
      $slack_message = [
        'text' => 'Impossible to back-merge *' . $ref . '* into *' . $branch . '*. *@' . $payload->commits[0]->author->username . '*, please fix the conflict(s) and raise a pull request.',
        'mrkdwn' => TRUE,
        'attachments' => [
          [
            'text' => implode("\n", $files),
            'color' => 'danger',
          ]
        ],
      ];
      notifySlack(json_encode($slack_message));

      // Abort the merge so repo is cleaned.
      $repo->execute(['merge', '--abort']);

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
 *
 * @param string $dir
 *
 * @return bool
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

/**
 * Send a message to Slack channel.
 *
 * @param string $data
 */
function notifySlack($data = '') {
  if (!empty(getenv('SLACK_HOOK_URL'))) {
    $ch = curl_init(getenv('SLACK_HOOK_URL'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data))
    );
    curl_exec($ch);
  }
  else {
    error_log('Slack hook url is not configured.');
  }

}