<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * ComponentAttributeBag
 *
 * Implements a lightweight version of Laravel's ComponentAttributeBag.
 * Allows components to receive and merge HTML attributes seamlessly,
 * supporting syntax like: <button {{ $attributes->merge(['class' => 'btn']) }}>
 */
class ComponentAttributeBag
{
    /** @var array Raw attributes */
    protected $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Merge default attributes with active tag attributes.
     * Appends 'class' attributes together.
     *
     * @param  array $defaults
     * @return self
     */
    public function merge(array $defaults = []): self
    {
        $merged = $this->attributes;

        foreach ($defaults as $key => $value) {
            if ($key === 'class' && isset($merged['class'])) {
                // Class merging: append classes together
                $merged['class'] = trim($value . ' ' . $merged['class']);
            } else {
                if (!isset($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }

        return new static($merged);
    }

    /**
     * Render the attributes as HTML attribute strings.
     *
     * @return string
     */
    public function __toString(): string
    {
        $html = [];
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $html[] = htmlspecialchars($key);
            } elseif ($value !== false && $value !== null) {
                $html[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }
        return implode(' ', $html);
    }
}
