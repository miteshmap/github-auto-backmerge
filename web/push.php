<?php


use Cz\Git\GitRepository;

function webhook_push_callback($payload) {
  $repo = GitRepository::cloneRepository('https://github.com/acquia-pso/alshaya', '/tmp/alshaya');
}