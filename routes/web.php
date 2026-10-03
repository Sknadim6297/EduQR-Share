        <?php

        use App\Http\Controllers\AuthController;
        use App\Http\Controllers\DocumentController;
        use App\Http\Controllers\PublicDocumentController;
        use Database\Seeders\AdminUserSeeder;
        use Illuminate\Http\Request;
        use Illuminate\Support\Facades\Artisan;
        use Illuminate\Support\Facades\Auth;
        use Illuminate\Support\Facades\Route;

        Route::get('/', function () {
            if (Auth::check()) {
                return redirect()->route('documents.index');
            }

            return view('welcome');
        })->name('home');

        Route::middleware('guest')->group(function () {
            Route::get('/login', [AuthController::class, 'create'])->name('login');
            Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
        });

        Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

        if (app()->environment('local')) {
            Route::middleware('throttle:3,10')->group(function () {
                Route::get('/seed-admin', function (Request $request) {
                    abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);

                    return view('local-admin-seed');
                })->name('local.admin-seed');

                Route::post('/seed-admin', function (Request $request) {
                    abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);

                    Artisan::call('db:seed', [
                        '--class' => AdminUserSeeder::class,
                        '--force' => true,
                    ]);

                    return redirect()->route('login')->with(
                        'status',
                        'Admin seeder completed. Existing accounts are left unchanged; check .env for the configured login.'
                    );
                })->name('local.admin-seed.store');
            });
        }

        Route::middleware('throttle:60,1')->prefix('share/{token}')->name('share.')->group(function () {
            Route::get('/', [PublicDocumentController::class, 'show'])->name('show');
            Route::get('/preview', [PublicDocumentController::class, 'preview'])->name('preview');
            Route::get('/download', [PublicDocumentController::class, 'download'])->name('download');
        });

        Route::middleware('auth')->prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::middleware('throttle:10,1')->group(function () {
                Route::post('/', [DocumentController::class, 'store'])->name('store');
                Route::put('/{document}', [DocumentController::class, 'update'])->whereNumber('document')->name('update');
                Route::delete('/{document}', [DocumentController::class, 'destroy'])->whereNumber('document')->name('destroy');
            });
            Route::get('/{document}/result', [DocumentController::class, 'result'])->whereNumber('document')->name('result');
            Route::get('/{document}/qr.png', [DocumentController::class, 'qr'])->whereNumber('document')->name('qr');
            Route::get('/{document}/qr-download', [DocumentController::class, 'downloadQr'])->whereNumber('document')->name('qr.download');
        });
