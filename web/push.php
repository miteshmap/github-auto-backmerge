<?php

use Cz\Git\GitRepository;

function webhook_push_callback($payload) {
  error_log(var_export($payload, 1));
  error_log('ici');

  $dir = '/tmp/alshaya';

  delete_directory($dir);

  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com/vbouchet31/test-php-git.git', $dir);
  }
  catch (Exception $e) {
    var_dump($e);
  }

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