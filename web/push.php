<?php


use Cz\Git\GitRepository;

function webhook_push_callback($payload) {
  $repo = GitRepository::cloneRepository('https://alshaya-github-bot:Alshaya%402019&github.com/acquia-pso/alshaya', '/tmp/alshaya');
}