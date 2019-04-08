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
  $dry_run = getenv('DRY_RUN') == 'true' ? TRUE : FALSE;

  $dir = '/tmp/' . uniqid('alshaya-');

  // Be sure the target directory does not exist yet.
  delete_directory($dir);

  // Clone the repository into the target directory.
  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com/' . getenv('GITHUB_REPO_ORG') . '/' . getenv('GITHUB_REPO_NAME') . '.git', $dir);
    $repo->fetch();
    $repo->execute(['config', '--local', 'user.name', getenv('GITHUB_USERNAME')]);
    $repo->execute(['config', '--local', 'user.email', getenv('GITHUB_EMAIL')]);
  }
  catch (Exception $e) {
    error_log('Impossible to clone repository into ' . $dir . '.');
    return;
  }

  global $upstream_branches;

  // Get the source branch from the payload.
  $ref = str_replace('refs/heads/', '', $payload->ref);

  if (empty($ref)) {
    error_log('Impossible to identify a valid ref from ' . $payload->ref);
    return;
  }

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

  // Identify the default target branch if any.
  if (isset($upstream_branches[$ref])) {
    $target_branches[] = $upstream_branches[$ref];
  }

  // Stop the process if there no target branch to back merge to.
  if (empty ($target_branches)) {
    error_log($ref . ' does not need to be back merged into any branch.');
    return;
  }

  // Print all the branches we should back merge to.
  error_log('We will merge ' . $ref . ' change into following branches: ' . implode(', ', $target_branches) . '.');

  // Add a security to avoid any merge into master. Given it has no parent
  // branch, it can only happen in case there is a bug in the script.
  if (in_array('master', $target_branches)) {
    error_log('');
    error_log('THERE IS SOMETHING WRONG, WE SHOULD NEVER BACK MERGE INTO MASTER.');
    error_log('');
    return;
  }

  // Browse all the target branches to back merge and push to the repository.
  foreach ($target_branches as $branch) {
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
      $slack_message = [
        'text' => 'Impossible to back-merge <https://github.com/' . getenv('GITHUB_REPO_ORG') . '/' . getenv('GITHUB_REPO_NAME') . '/compare/' . $branch . '...' . $ref . '?expand=1|*' . $ref . '* into *' . $branch . '*>. *@' . $payload->commits[0]->author->username . '*, please fix the conflict(s) and raise a pull request.',
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

    if (!$dry_run) {
      try {
        error_log('Push change to ' . $branch);
        $repo->execute(['push', 'origin', $branch]);
      }
      catch (GitException $e) {
        // @TODO: Notify about the error. Concurrent merges?
        error_log('Impossible to push into ' . $branch);
        continue;
      }
    }
    else {
      error_log('Running in dry-run mode so we don\'t push anything.');
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