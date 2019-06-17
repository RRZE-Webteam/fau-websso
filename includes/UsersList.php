<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

class UsersList
{
    public static function init() {
        add_filter('manage_users_columns', [__CLASS__, 'attributes']);
        add_action('manage_users_custom_column', [__CLASS__, 'attributesColumns'], 10, 3);
        add_filter('wpmu_users_columns', [__CLASS__, 'attributes']);
        add_action('wpmu_users_custom_column', [__CLASS__, 'attributesColumns'], 10, 3);
    }

    public static function attributes($columns)
    {
        $columns['attributes'] = __('Attributes', 'fau-websso');
        return $columns;
    }

    public static function attributesColumns($value, $columnName, $userId)
    {
        if ('attributes' != $columnName) {
            return $value;
        }

        $attributes = array();

        $eduPersonAffiliation = get_user_meta($userId, 'edu_person_affiliation', true);
        if ($eduPersonAffiliation) {
            $attributes[] = is_array($eduPersonAffiliation) ? implode('<br>', $eduPersonAffiliation) : $eduPersonAffiliation;
        }

        return implode('<br>', $attributes);
    }
}
