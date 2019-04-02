<?php

use Cz\Git\GitRepository;

function webhook_push_callback($payload) {
  try {
    $repo = GitRepository::cloneRepository('git+ssh://git@github.com/acquia-pso/alshaya.git', '/tmp/alshaya');
    var_dump($repo->getBranches());
    $repo->fetch();
    //$repo->createBranch('vbo-test');
    //$repo->push();
  }
  catch (Exception $e) {
    var_dump($e);
  }

}