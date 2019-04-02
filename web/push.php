<?php


function webhook_push_callback($payload) {
  $repo = \Cz\Git\GitRepository::cloneRepository('https://github.com/acquia-pso/alshaya/pulls', '/tmp/alshaya');
}