# X32-Mixbus-webconfig

## Describtion:
Make your monitor/mixbus mix on the Behringer X32 console through a web application.

The idea for this project was, that the band can make there monitor mix on the stage, with there smartphones.

This is just some hobby project. I do not give support for this.
But if it is just some small question, then I will probable answer.

## Requirements:
1. A webserver.
2. The webserver needs to run php.
3. The webserver must have access to the X32 over the network

## Tested with:
- apache2
- php 7.4 and 8.2

## Installation:
1. Copy Repo inside a folter witch is accessable over the webserver.
2. Open file config.ini
   1.  Set parameter X32_IP to the IP of your X32.
   2.  Config parameter MIXBUS_LIST. This is the list of mixbuses you like to mix through the application.
  
## Use:
Open the website on your webserver mix.html.
Choose an Mixbus on top.
Add some channels.
Make your mix.

## Examples:
### On PC
![Screenshot 2024-02-18 162317](https://github.com/schattenmann80/X32-Mixbus-webconfig/assets/22788790/8f62b185-a928-416f-bf62-602b3f765c81)

### ON Mobile
![Screenshot 2024-02-18 162410](https://github.com/schattenmann80/X32-Mixbus-webconfig/assets/22788790/3a4fe4f1-c662-4397-affa-cdc72a0f01c8)

### With config DISPLAY_CHANNEL_COLOR set to ON
![Screenshot 2024-02-18 162527](https://github.com/schattenmann80/X32-Mixbus-webconfig/assets/22788790/4ccbba4b-49e8-443e-bee6-c37dfeeeff80)
