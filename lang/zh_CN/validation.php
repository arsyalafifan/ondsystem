<?php

return [
    'required' => '请填写 :attribute。',
    'email' => ':attribute 格式不正确。',
    'numeric' => ':attribute 必须是数字。',
    'integer' => ':attribute 必须是整数。',
    'boolean' => ':attribute 只能为真或假。',
    'array' => ':attribute 必须是数组。',
    'date' => ':attribute 不是有效日期。',
    'image' => ':attribute 必须是图片。',
    'file' => ':attribute 必须是文件。',
    'mimes' => ':attribute 必须是以下格式：:values。',
    'unique' => ':attribute 已被使用。',
    'exists' => '所选的 :attribute 无效。',
    'confirmed' => ':attribute 确认不一致。',
    'not_in' => '所选的 :attribute 无效。',
    'in' => '所选的 :attribute 无效。',
    'string' => ':attribute 必须是文本。',
    'max' => [
        'numeric' => ':attribute 不得大于 :max。',
        'file' => ':attribute 不得大于 :max KB。',
        'string' => ':attribute 不得超过 :max 个字符。',
        'array' => ':attribute 不得超过 :max 项。',
    ],
    'min' => [
        'numeric' => ':attribute 至少为 :min。',
        'file' => ':attribute 至少 :min KB。',
        'string' => ':attribute 至少 :min 个字符。',
        'array' => ':attribute 至少 :min 项。',
    ],
    'between' => [
        'numeric' => ':attribute 必须在 :min 与 :max 之间。',
        'file' => ':attribute 必须在 :min 与 :max KB 之间。',
        'string' => ':attribute 必须在 :min 与 :max 个字符之间。',
        'array' => ':attribute 必须包含 :min 至 :max 项。',
    ],
    'custom' => [
        'attribute-name' => [
            'rule-name' => '',
        ],
    ],
    'attributes' => [

    ],
];
