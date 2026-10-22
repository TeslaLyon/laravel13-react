<?php

namespace App\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function profileRules(?int $userId = null): array
    {
        $rules = [
            'nickname' => $this->nicknameRules($userId),
            'email' => $this->emailRules($userId),
        ];

        // 仅在注册时（未传入 userId）强制校验用户名；用户注册后不可随意修改用户名
        if ($userId === null) {
            $rules['name'] = $this->nameRules();
        }

        return $rules;
    }

    /**
     * Get the validation rules used to validate user names (系统用户名/账号标识).
     *
     * @return array<int, ValidationRule|array<mixed>|string|Closure>
     */
    protected function nameRules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:20',
            // 须以字母或数字开头，仅允许字母、数字、下划线(_)和减号(-)，不支持特殊字符与表情
            'regex:/^[a-zA-Z0-9][a-zA-Z0-9_-]{2,19}$/',
            // 系统保留字黑名单，防止与关键路由及系统角色冲突
            Rule::notIn([
                'admin', 'administrator', 'root', 'system', 'api', 'help', 'official',
                'support', 'settings', 'user', 'users', 'video', 'videos', 'forum',
                'thread', 'terms', 'privacy', 'login', 'register', 'auth', 'null',
                'undefined', 'smzdk', 'bot', 'guest', 'mod', 'moderator', 'dashboard',
            ]),
            Rule::unique(User::class, 'name'),
        ];
    }

    /**
     * Get the validation rules used to validate user nicknames (社区展示昵称).
     *
     * @return array<int, ValidationRule|array<mixed>|string|Closure>
     */
    protected function nicknameRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'min:2',
            'max:30',
            $userId === null
                ? Rule::unique(User::class, 'nickname')
                : Rule::unique(User::class, 'nickname')->ignore($userId),
            // 自定义检查：过滤不可见控制字符，并确保有效文字长度 >= 2，不能全为空白或隐形符号
            function (string $attribute, mixed $value, Closure $fail) {
                if (!is_string($value)) {
                    return;
                }
                // 移除零宽字符和不可见控制符
                $cleaned = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value);
                if (mb_strlen(trim($cleaned)) < 2) {
                    $fail('昵称不能全为空白或不可见字符，有效长度至少为 2 个字符。');
                }
            },
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * 针对个人资料验证的中文友好提示信息
     *
     * @return array<string, string>
     */
    protected function profileValidationMessages(): array
    {
        return [
            'name.required' => '请输入用户名。',
            'name.min' => '用户名至少需要 3 个字符。',
            'name.max' => '用户名不能超过 20 个字符。',
            'name.regex' => '用户名格式不符合要求：须以字母或数字开头，仅允许字母、数字、下划线(_)和减号(-)，不支持特殊字符与表情。',
            'name.not_in' => '该用户名是系统保留字，请使用其他用户名。',
            'name.unique' => '该用户名已被占用，请尝试其他名称。',

            'nickname.required' => '请输入昵称。',
            'nickname.min' => '昵称至少需要 2 个字符。',
            'nickname.max' => '昵称不能超过 30 个字符。',
            'nickname.unique' => '该昵称已被使用，请换一个昵称。',

            'email.required' => '请输入电子邮箱地址。',
            'email.email' => '请输入有效的电子邮箱地址。',
            'email.max' => '电子邮箱地址过长。',
            'email.unique' => '该邮箱已被注册，请直接登录或使用其他邮箱。',
        ];
    }
}

