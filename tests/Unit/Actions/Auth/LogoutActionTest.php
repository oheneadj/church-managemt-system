<?php

declare(strict_types=1);

use App\Actions\Auth\LogoutAction;
use App\DTOs\Auth\LogoutDTO;
use App\Models\PersonalAccessToken;
use App\Services\AuthSessionService;
use Illuminate\Contracts\Session\Session;

it('deletes the token of an API client', function (): void {
    $token = Mockery::mock(PersonalAccessToken::class)->makePartial();
    $token->shouldReceive('delete')->once();
    $user = unitUser()->withAccessToken($token);
    $sessions = Mockery::mock(AuthSessionService::class);
    $sessions->shouldReceive('endBrowserSession')->never();

    (new LogoutAction($sessions))->execute(new LogoutDTO($user, Mockery::mock(Session::class)));
});

it('closes the cookie session of the browser app', function (): void {
    $session = Mockery::mock(Session::class);
    $sessions = Mockery::mock(AuthSessionService::class);
    $sessions->shouldReceive('endBrowserSession')->once()->with($session);

    (new LogoutAction($sessions))->execute(new LogoutDTO(unitUser(), $session));
});

it('does nothing more when there is neither a token nor a session', function (): void {
    $sessions = Mockery::mock(AuthSessionService::class);
    $sessions->shouldReceive('endBrowserSession')->never();

    (new LogoutAction($sessions))->execute(new LogoutDTO(unitUser()));
});
