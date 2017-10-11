# ChatBot User Interface
## Installation
### Composer
- Add this as a repository in your composer.json file
```
{
    "type": "package",
    "package": {
        "name": "brainsum/tcb_ui",
        "version": "0.1.0",
        "type": "drupal-module",
        "source": {
            "url": "https://github.com/brainsum/tcb_ui",
            "type": "git",
            "reference": "master"
        }
    }
}
```

- Require as a dependency
```composer require brainsum/tcb_ui```
- Then enable the module

## Usage
- Add the necessary configuration on the settings form
- Make sure to have the bot endpoint available
    - It has to have a ```/chatbot``` endpoint for POST
    - The request has to contain the following: ```form_params: { user_input: "message" } ```
    - The response has to contain the following key: ```(string) category```
    - The category should be one of these: ```personal, devops_error``` or a taxonomy term ID used in the ```field_keyword``` field of a ```node```

## Config overrides
You can use the following code in your ```settings.php```, if needed:
```
$config['tcb_ui.chatbot_server_list']['server_list']['hosts'] = [
  'http://localhost:5000',
];

$config['tcb_ui.settings']['settings'] = [
  'email_address' => 'support@example.com',
  'bot_display_name' => 'Ivan',
  'conversation_header' => 'Site support',
];
```
