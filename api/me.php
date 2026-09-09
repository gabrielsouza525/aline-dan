<?php
/** Sessão atual: GET → {user} ou {user: null}. */
declare(strict_types=1);
require __DIR__ . '/config.php';

require_method('GET');

json_response(200, ['user' => current_user()]);
