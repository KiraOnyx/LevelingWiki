<?php
declare(strict_types=1);
return [
    'client_id'=>getenv('DISCORD_CLIENT_ID') ?: '',
    'client_secret'=>getenv('DISCORD_CLIENT_SECRET') ?: '',
    'guild_id'=>getenv('DISCORD_GUILD_ID') ?: 'xxxx',
    'redirect_uri'=>getenv('DISCORD_REDIRECT_URI') ?: 'http://localhost:8080/auth/discord/callback',
    'role_ttl'=>86400,
    // Le serveur simulé n’est accessible que dans la base de test, jamais via paramètre HTTP.
    'api_base'=>getenv('APP_ENV')==='test' && getenv('DB_DATABASE')==='levelingwiki_test' ? (getenv('DISCORD_TEST_API') ?: 'https://discord.com/api') : 'https://discord.com/api',
];
