## DosjeIN Vkontraktor

Define Variables in **.env** 

- VKTOKEN - Vkontakti Oauth Token . How to get it more details in [getjump/VkApiPHP](https://github.com/getjump/VkApiPHP#explanation) repository
- CHATBOT_URL - [dosjein/chatbot-rnn](https://github.com/dosjein/chatbot-rnn) instance url
- CHATBOT_TOKEN - Ident or Token created by asking NEW in ChatBot instance. [Small setup notes](https://github.com/dosjein/chatbot-rnn#usage)

If any question - feel free to ask at **dosjein[at]gmail[etc]** referencing to Ronalds Sovas or John Dosje

DosjeIN Vkontraktor is INTENDED TO BE USED IN SCOPE OF LAW. 
Please be always aware of what you’re doing. 
I AM NOT RESPONSIBLE FOR ANY DAMAGES THAT HAPPEN BY USING THIS SOFTWARE!


##16.07.2018 

#1 /var/www/vendor/irazasyed/telegram-bot-sdk/src/Api.php(1014): Telegram\Bot\TelegramClient->sendRequest(Object(Telegram\Bot\TelegramRequest))
#2 /var/www/vendor/irazasyed/telegram-bot-sdk/src/Api.php(957): Telegram\Bot\Api->sendRequest('POST', 'getMe', Array)
#3 /var/www/vendor/irazasyed/telegram-bot-sdk/src/Api.php(269): Telegram\Bot\Api->post('getMe')
#4 /var/www/vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php(215): Telegram\Bot\Api->getMe()
#5 /var/www/app/Console/Commands/TelegramTest.php(230): Illuminate\Support\Facades\Facade::__callStatic('getMe', Array)
#6 [internal function]: App\Console\Commands\TelegramTest->handle()




## Laravel PHP Framework

[![Build Status](https://travis-ci.org/laravel/framework.svg)](https://travis-ci.org/laravel/framework)
[![Total Downloads](https://poser.pugx.org/laravel/framework/d/total.svg)](https://packagist.org/packages/laravel/framework)
[![Latest Stable Version](https://poser.pugx.org/laravel/framework/v/stable.svg)](https://packagist.org/packages/laravel/framework)
[![Latest Unstable Version](https://poser.pugx.org/laravel/framework/v/unstable.svg)](https://packagist.org/packages/laravel/framework)
[![License](https://poser.pugx.org/laravel/framework/license.svg)](https://packagist.org/packages/laravel/framework)

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Laravel attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as authentication, routing, sessions, queueing, and caching.

Laravel is accessible, yet powerful, providing powerful tools needed for large, robust applications. A superb inversion of control container, expressive migration system, and tightly integrated unit testing support give you the tools you need to build any application with which you are tasked.

## Official Documentation

Documentation for the framework can be found on the [Laravel website](http://laravel.com/docs).

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](http://laravel.com/docs/contributions).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell at taylor@laravel.com. All security vulnerabilities will be promptly addressed.

### License

The Laravel framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
