<?php

namespace {
    if (!function_exists('\is_countable')) {
        function is_countable($item) {
            return is_array($item) || $item instanceof \Countable;
        }
    }
};
