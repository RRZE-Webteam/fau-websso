<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

class UsersList
{
    public function onLoaded()
    {
        add_filter('manage_users_columns', [$this, 'attributes']);
        add_action('manage_users_custom_column', [$this, 'attributesColumns'], 10, 3);
        add_filter('wpmu_users_columns', [$this, 'attributes']);
        add_action('wpmu_users_custom_column', [$this, 'attributesColumns'], 10, 3);
    }

    public function attributes($columns)
    {
        $columns['attributes'] = __('Attributes', 'fau-websso');
        return $columns;
    }

    public function attributesColumns($value, $columnName, $userId)
    {
        if ('attributes' != $columnName) {
            return $value;
        }

        $attributes = [];

        $eduPersonAffiliation = get_user_meta($userId, 'edu_person_affiliation', true);
        if ($eduPersonAffiliation) {
            $attributes[] = is_array($eduPersonAffiliation) ? implode('<br>', $eduPersonAffiliation) : $eduPersonAffiliation;
        }
        
        $eduPersonEntitlement = get_user_meta($userId, 'edu_person_entitlement', true);
        if ($eduPersonEntitlement) {
            $attributes[] = is_array($eduPersonEntitlement) ? implode('<br>', $eduPersonEntitlement) : $eduPersonEntitlement;
        }
        
        return implode('<br>', $attributes);
    }
}
