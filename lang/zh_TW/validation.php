<?php

return [
    'required' => '請填寫 :attribute。',
    'email' => ':attribute 格式不正確。',
    'numeric' => ':attribute 必須是數字。',
    'integer' => ':attribute 必須是整數。',
    'boolean' => ':attribute 只能為真或假。',
    'array' => ':attribute 必須是陣列。',
    'date' => ':attribute 不是有效日期。',
    'image' => ':attribute 必須是圖片。',
    'file' => ':attribute 必須是檔案。',
    'mimes' => ':attribute 必須是以下格式：:values。',
    'unique' => ':attribute 已被使用。',
    'exists' => '所選的 :attribute 無效。',
    'confirmed' => ':attribute 確認不一致。',
    'not_in' => '所選的 :attribute 無效。',
    'in' => '所選的 :attribute 無效。',
    'string' => ':attribute 必須是文字。',
    'max' => [
        'numeric' => ':attribute 不得大於 :max。',
        'file' => ':attribute 不得大於 :max KB。',
        'string' => ':attribute 不得超過 :max 個字元。',
        'array' => ':attribute 不得超過 :max 項。',
    ],
    'min' => [
        'numeric' => ':attribute 至少為 :min。',
        'file' => ':attribute 至少 :min KB。',
        'string' => ':attribute 至少 :min 個字元。',
        'array' => ':attribute 至少 :min 項。',
    ],
    'between' => [
        'numeric' => ':attribute 必須在 :min 與 :max 之間。',
        'file' => ':attribute 必須在 :min 與 :max KB 之間。',
        'string' => ':attribute 必須在 :min 與 :max 個字元之間。',
        'array' => ':attribute 必須包含 :min 至 :max 項。',
    ],
    'custom' => [
        'attribute-name' => [
            'rule-name' => '',
        ],
    ],
    'attributes' => [

    ],
];
