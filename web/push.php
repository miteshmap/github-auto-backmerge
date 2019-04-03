<?php

use Cz\Git\GitRepository;

function webhook_push_callback($payload) {
  $dir = '/tmp/alshaya';

  rmdir($dir);

  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com/acquia-pso/alshaya.git', $dir);
    var_dump($repo->getBranches());
    $repo->fetch();
    //$repo->createBranch('vbo-test');
    //$repo->push();
  }
  catch (Exception $e) {
    var_dump($e);
  }

}