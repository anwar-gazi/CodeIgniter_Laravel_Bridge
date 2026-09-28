<?php
namespace AnwarGazi\CiLaravelSupport;

class View
{
    /** @var string */
    protected $view;

    /** @var array */
    protected $data;

    /** @var string|null */
    protected $layout = null;

    public function __construct(string $view, array $data = [])
    {
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Specify a layout wrapper template (CodeIgniter style layout).
     *
     * @param  string $layout
     * @return self
     */
    public function layout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Render the Blade template.
     *
     * @return string
     */
    public function render(): string
    {
        $rendered = BladeEngine::render($this->view, $this->data);

        if ($this->layout) {
            $CI =& get_instance();
            $data = $this->data;
            $data['blade_body'] = $rendered;
            $data['main_content'] = '_blade_body_passthrough';

            $bladeLayout = str_replace('/', '.', $this->layout);
            $resolvedLayout = BladeEngine::resolveViewName($bladeLayout);

            if (BladeEngine::factory()->exists($resolvedLayout)) {
                return BladeEngine::render($bladeLayout, $data);
            }

            return $CI->load->view($this->layout, $data, true);
        }

        return $rendered;
    }

    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            if (function_exists('log_message')) {
                log_message('error', 'Blade view rendering failed: ' . $e->getMessage());
            }

            return '';
        }
    }
}
