<?php

namespace App\Exceptions;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (
            NotFoundHttpException $exception,
            Request $request
        ) {
            $previous = $exception->getPrevious();

            if (
                $request->is('api/v1/attendance-records/*')
                && $previous instanceof ModelNotFoundException
                && $previous->getModel() === Attendance::class
            ) {
                return response()->json([
                    'error' => '勤怠情報が見つかりませんでした。',
                ], 404);
            }

            return null;
        });
        $this->renderable(function (
            AccessDeniedHttpException $exception,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'この操作を実行する権限がありません。',
                ], 403);
            }

            return null;
        });
    }
}
