<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (TokenMismatchException $exception, $request) {
            $message = localize(
                'session_expired_login_again',
                'សម័យប្រើប្រាស់បានផុតកំណត់ សូមចូលប្រើម្តងទៀត។'
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()
                ->route('login')
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('warning', $message);
        });
    }
}
