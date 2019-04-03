<?php

use Cz\Git\GitRepository;

function webhook_push_callback($payload) {
  $dir = '/tmp/alshaya';

  if (is_dir($dir)) {
    rmdir($dir);
  }

  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com:vbouchet31/test-php-git.git', $dir);
    var_dump($payload);
    $repo->fetch();
  }
  catch (Exception $e) {
    var_dump($e);
  }

}