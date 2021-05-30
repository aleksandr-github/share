<?php

namespace App\Model\App\Result;

use App\Exception\ModelNotFoundException;

class HorseResultDataSet
{
    /**
     * @var HorseResultDataModel[]
     */
    protected $elements;

    public function __construct(array $elements = null)
    {
        if ($elements) {
            $this->setElements($elements);
        }
    }

    public function add(HorseResultDataModel $horseResultDataModel)
    {
        array_push($this->elements, $horseResultDataModel);
    }

    public function all(): array
    {
        return $this->elements;
    }

    public function has(HorseResultDataModel $horseResultDataModel): bool
    {
        foreach ($this->elements as $element) {
            if ($element === $horseResultDataModel) {
                return true;
            }
        }

        return false;
    }

    public function setElements(array $elements): HorseResultDataSet
    {
        $this->elements = $elements;

        return $this;
    }

    public function existsElementWithHorseId(int $horseId): bool
    {
        foreach ($this->elements as $element) {
            if ($element->getHorseId() == $horseId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $horseId
     * @return HorseResultDataModel[]
     */
    public function getElementsWithHorseId(int $horseId): array
    {
        $elements = [];

        foreach ($this->elements as $element) {
            if ($element->getHorseId() == $horseId) {
                $elements[] = $element;
            }
        }

        return $elements;
    }
}