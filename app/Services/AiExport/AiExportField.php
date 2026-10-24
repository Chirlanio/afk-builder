<?php

namespace App\Services\AiExport;

use Illuminate\Validation\Rule;

/**
 * Um parâmetro de Objetivo (fase, torre, lado...). Gera a regra de validação
 * do backend e a descrição que o modal usa para desenhar o campo: as duas
 * saem da mesma definição.
 */
final readonly class AiExportField
{
    /**
     * @param  array<string, string>  $options  valor => rótulo (campo de lista)
     * @param  ?string  $pattern  regex sem delimitadores (campo de texto livre)
     */
    private function __construct(
        public string $name,
        public string $label,
        public array $options = [],
        public ?string $pattern = null,
        public ?string $placeholder = null,
        public ?string $default = null,
    ) {}

    /**
     * @param  array<string, string>  $options
     */
    public static function select(string $name, string $label, array $options, ?string $default = null): self
    {
        return new self($name, $label, options: $options, default: $default);
    }

    public static function text(string $name, string $label, string $pattern, string $placeholder): self
    {
        return new self($name, $label, pattern: $pattern, placeholder: $placeholder);
    }

    /**
     * Com valor padrão o campo é opcional: quem chama aplica o padrão.
     *
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        if ($this->pattern !== null) {
            return ['required', 'string', 'regex:/'.$this->pattern.'/'];
        }

        return [$this->default !== null ? 'nullable' : 'required', Rule::in(array_map('strval', array_keys($this->options)))];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->pattern !== null
            ? ['name' => $this->name, 'label' => $this->label, 'pattern' => $this->pattern, 'placeholder' => $this->placeholder]
            : [
                'name' => $this->name,
                'label' => $this->label,
                'options' => array_map(
                    fn (string|int $value, string $label) => ['value' => (string) $value, 'label' => $label],
                    array_keys($this->options),
                    $this->options,
                ),
                'default' => $this->default,
            ];
    }
}
