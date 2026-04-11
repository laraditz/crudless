<?php

namespace Laraditz\Crudless;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BaseAuthController extends Controller
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * The Eloquent model used for authentication.
     * Defaults to App\Models\User.
     *
     * @var string
     */
    protected string $userModel = \App\Models\User::class;

    /**
     * Form Request class for register().
     * When declared, takes priority over registerRules().
     *
     * @var string|null
     */
    protected ?string $registerRequest = null;

    /**
     * Form Request class for login().
     * When declared, takes priority over loginRules().
     *
     * @var string|null
     */
    protected ?string $loginRequest = null;

    // -------------------------------------------------------------------------
    // Validation rules — override in child to customise
    // -------------------------------------------------------------------------

    protected function registerRules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique($this->userModel)],
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    protected function loginRules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }

    // -------------------------------------------------------------------------
    // Before hooks — throw to abort
    // -------------------------------------------------------------------------

    protected function beforeRegister(array $data): void {}
    protected function beforeLogin(array $data): void {}
    protected function beforeLogout(mixed $user): void {}

    // -------------------------------------------------------------------------
    // After hooks — must return the value that goes in the response
    // -------------------------------------------------------------------------

    protected function afterRegister(mixed $user): mixed { return $user; }
    protected function afterLogin(mixed $user, string $token): mixed { return ['token' => $token]; }
    protected function afterLogout(): void {}

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function resolveRegisterData(Request $request): array
    {
        if ($this->registerRequest) {
            return app($this->registerRequest)->validated();
        }

        $rules = $this->registerRules();

        return $rules
            ? $request->validate($rules)
            : $request->all();
    }

    protected function resolveLoginData(Request $request): array
    {
        if ($this->loginRequest) {
            return app($this->loginRequest)->validated();
        }

        $rules = $this->loginRules();

        return $rules
            ? $request->validate($rules)
            : $request->all();
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    public function register(Request $request)
    {
        $data = $this->resolveRegisterData($request);

        $this->beforeRegister($data);

        // Strip password_confirmation (added by 'confirmed' rule) and re-hash password
        $user = $this->userModel::create(array_merge(
            Arr::except($data, ['password', 'password_confirmation']),
            ['password' => Hash::make($data['password'])]
        ));

        $user = $this->afterRegister($user);

        return response()->api()->created($user);
    }

    public function login(Request $request)
    {
        $data = $this->resolveLoginData($request);

        $this->beforeLogin($data);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->api()->httpCode(401)->failed();
        }

        $user  = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        $payload = $this->afterLogin($user, $token);

        return response()->api()->data($payload)->success();
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $this->beforeLogout($user);

        if ($token = $user->currentAccessToken()) {
            $token->delete();
        }

        $this->afterLogout();

        return response()->api()->httpCode(204)->success();
    }
}
