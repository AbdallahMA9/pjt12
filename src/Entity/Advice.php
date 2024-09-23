<?php

namespace App\Entity;

use App\Repository\AdviceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdviceRepository::class)]
class Advice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Month>
     */
    #[ORM\ManyToMany(targetEntity: Month::class, inversedBy: 'advice')]
    private Collection $month;

    #[ORM\Column(length: 255)]
    private ?string $content = null;

    public function __construct()
    {
        $this->month = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Month>
     */
    public function getMonth(): Collection
    {
        return $this->month;
    }

    public function addMonth(Month $month): static
    {
        if (!$this->month->contains($month)) {
            $this->month->add($month);
        }

        return $this;
    }

    public function removeMonth(Month $month): static
    {
        $this->month->removeElement($month);

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }
}
