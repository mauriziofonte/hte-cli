<?php

namespace Mfonte\HteCli\Traits;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;

/**
 * Trait for interactive CLI menus with arrow key navigation.
 *
 * Uses php-school/cli-menu to provide Laravel Prompts-style selection menus.
 * Falls back to standard choice() when running in non-interactive mode.
 */
trait InteractiveMenu
{
    /**
     * Display an interactive menu with arrow key navigation.
     *
     * @param string $title Menu title
     * @param array $options Array of options (can be associative ['label' => 'value'] or indexed)
     * @param mixed $default Default value (optional)
     * @return mixed Selected value
     */
    protected function interactiveSelect(string $title, array $options, $default = null)
    {
        // Fallback to choice() if not interactive (pipe, --no-interaction, or non-TTY)
        if (!$this->isInteractiveTerminal()) {
            return $this->fallbackChoice($title, $options, $default);
        }

        $selectedValue = $default;

        // Handle both associative and indexed arrays
        $isAssociative = array_keys($options) !== range(0, count($options) - 1);

        $builder = (new CliMenuBuilder())
            ->setTitle($title)
            ->setWidth($this->calculateMenuWidth($options, $isAssociative))
            ->setPadding(2, 4)
            ->setMarginAuto()
            ->setForegroundColour('white')
            ->setBackgroundColour('blue');

        foreach ($options as $key => $value) {
            $label = $isAssociative ? (string) $key : (string) $value;
            $returnValue = $value;

            $builder->addItem($label, function (CliMenu $menu) use ($returnValue, &$selectedValue) {
                $selectedValue = $returnValue;
                $menu->close();
            });
        }

        $menu = $builder->build();

        try {
            $menu->open();
        } catch (\Exception $e) {
            // If cli-menu fails (e.g., terminal issues), fallback to choice
            return $this->fallbackChoice($title, $options, $default);
        }

        return $selectedValue;
    }

    /**
     * Display a confirmation menu (Yes/No) with arrow key navigation.
     *
     * @param string $question The question to ask
     * @param bool $default Default value
     * @return bool User's choice
     */
    protected function interactiveConfirm(string $question, bool $default = false): bool
    {
        // Fallback to standard confirm if not interactive
        if (!$this->isInteractiveTerminal()) {
            return $this->confirm($question, $default);
        }

        $options = $default
            ? ['Yes (default)' => true, 'No' => false]
            : ['Yes' => true, 'No (default)' => false];

        return (bool) $this->interactiveSelect($question, $options, $default);
    }

    /**
     * Check if we're running in an interactive terminal.
     *
     * @return bool
     */
    protected function isInteractiveTerminal(): bool
    {
        // Check for --no-interaction option
        if (method_exists($this, 'option') && $this->option('no-interaction')) {
            return false;
        }

        // Check if STDIN is a TTY
        if (function_exists('posix_isatty') && !posix_isatty(STDIN)) {
            return false;
        }

        // Check for TERM environment variable
        if (empty(getenv('TERM'))) {
            return false;
        }

        return true;
    }

    /**
     * Fallback to standard choice() for non-interactive terminals.
     *
     * @param string $title
     * @param array $options
     * @param mixed $default
     * @return mixed
     */
    protected function fallbackChoice(string $title, array $options, $default = null)
    {
        $isAssociative = array_keys($options) !== range(0, count($options) - 1);
        $labels = $isAssociative ? array_keys($options) : array_values($options);

        // Find default index
        $defaultIndex = 0;
        if ($default !== null) {
            if ($isAssociative) {
                $defaultIndex = array_search($default, array_values($options));
            } else {
                $defaultIndex = array_search($default, $options);
            }
            if ($defaultIndex === false) {
                $defaultIndex = 0;
            }
        }

        $selected = $this->choice($title, $labels, $defaultIndex);

        // Return the value, not the label
        if ($isAssociative) {
            return $options[$selected] ?? $default;
        }

        return $selected;
    }

    /**
     * Calculate optimal menu width based on content.
     *
     * @param array $options
     * @param bool $isAssociative
     * @return int
     */
    protected function calculateMenuWidth(array $options, bool $isAssociative): int
    {
        $maxLength = 0;

        foreach ($options as $key => $value) {
            $label = $isAssociative ? (string) $key : (string) $value;
            $length = mb_strlen($label);
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }

        // Add padding: marker (2) + padding (8) + some extra space
        $width = $maxLength + 15;

        // Clamp between 40 and 80 characters
        return max(40, min(80, $width));
    }
}
