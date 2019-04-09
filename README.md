### Purpose
This script aims to automate the back-merge to downstream branches. When
merging something on uat branch for a hotfix (for example), we often want to
see this change applied to downstream branches such as qa or develop branches.
In the same way, when something is merged, we want it to be applied to
feature branches. That way, conflicts are tackled quickly after the merge
instead of having time to time manual merge.

### App configuration
Create a new app and push this code. Procfile will automatically makes Heroku
to detect a web application and will make php, nginx, ... available.

#### General
Create a technical account (or use your) to be used by the script as identity.
Provide the repositoy organization, repository name, user name and email
to the heroku app.
```
heroku config:set GITHUB_REPO_ORG=:org-name
heroku config:set GITHUB_REPO_NAME=:repo-name
heroku config:set GITHUB_USERNAME=:user-name
heroku config:set GITHUB_EMAIL=:user-email
```

#### SSH keys
An additional package ([buildpack](https://elements.heroku.com/buildpacks/debitoor/ssh-private-key-buildpack)) is required to manage the ssh key required
for the script to push to the target Github repository.

Give the ssh key of the Github user to the keroku app.
```
heroku config:set SSH_KEY=$(cat :path-to-the-key | base64)
```

#### Github webook
For the heroku app to be notified when something is pushed, a webhook mst be
created. On settings > hooks (https://github.com/:org/:repo/settings/hooks),
create a webhook which the payload url is
https://:heroku-app.herokuapp.com/listener.php and register for push event.

#### Slack notification
To notify Slack when a back merge is not possible, create a Slack app,
configure an "Incoming Webhooks" and provide the webhook URL to the heroku app.
```
heroku config:set SLACK_HOOK_URL=:slack-webhook-url
```

### Branches
By default, the scripts assumes the following back merges: Master into uat, uat
into qa, qa into develop. Features branches must follow a naming convention to
be properly detected: develop-feature-1 will automatically receive back merge
when something is merged into develop.
