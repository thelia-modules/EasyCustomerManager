<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EasyCustomerManager\Service;

use Symfony\Component\HttpFoundation\InputBag;

/**
 * What the administrator asked for on the list page, read from the query string and made safe:
 * an unknown page size, sort column or malformed date falls back to its default.
 */
final readonly class CustomerListFilters
{
    public const PAGE_SIZES = [10, 25, 50, 100];

    public const DEFAULT_PAGE_SIZE = 25;

    /** A search shorter than this matches too much to be worth a scan of the customer table. */
    public const MIN_SEARCH_LENGTH = 3;

    private const KNOWN_PARAMETERS = ['search', 'country', 'from', 'to', 'order', 'direction', 'page', 'length', '_token', 'return'];

    public function __construct(
        public string $search = '',
        public int $countryId = 0,
        public ?string $registeredFrom = null,
        public ?string $registeredTo = null,
        public CustomerSort $sort = CustomerSort::Registration,
        public bool $descending = true,
        public int $page = 1,
        public int $pageSize = self::DEFAULT_PAGE_SIZE,
        /** @var array<string, string> parameters of the query the module does not know (fields added by a listener), kept in every link */
        public array $extraParameters = [],
    ) {
    }

    /**
     * @param InputBag<mixed> $input
     */
    public static function fromInput(InputBag $input): self
    {
        $pageSize = $input->getInt('length', self::DEFAULT_PAGE_SIZE);

        return new self(
            search: trim($input->getString('search')),
            countryId: max(0, $input->getInt('country')),
            registeredFrom: self::date($input->getString('from')),
            registeredTo: self::date($input->getString('to')),
            sort: CustomerSort::fromInput($input->get('order')),
            descending: 'asc' !== $input->getString('direction'),
            page: max(1, $input->getInt('page', 1)),
            pageSize: \in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : self::DEFAULT_PAGE_SIZE,
            extraParameters: array_map(
                strval(...),
                array_filter(
                    array_diff_key($input->all(), array_flip(self::KNOWN_PARAMETERS)),
                    static fn (mixed $value): bool => \is_scalar($value) && '' !== $value,
                ),
            ),
        );
    }

    public function hasSearch(): bool
    {
        return mb_strlen($this->search) >= self::MIN_SEARCH_LENGTH;
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->search,
            $this->countryId,
            $this->registeredFrom,
            $this->registeredTo,
            $this->sort,
            $this->descending,
            max(1, $page),
            $this->pageSize,
            $this->extraParameters,
        );
    }

    /**
     * The query string that gives this same list back, without the page.
     *
     * @return array<string, int|string>
     */
    public function toQueryParameters(): array
    {
        return array_filter($this->extraParameters + [
            'search' => $this->search,
            'country' => $this->countryId,
            'from' => $this->registeredFrom,
            'to' => $this->registeredTo,
            'order' => $this->sort->value,
            'direction' => $this->descending ? 'desc' : 'asc',
            'length' => $this->pageSize,
        ], static fn (int|string|null $value): bool => null !== $value && '' !== $value && 0 !== $value);
    }

    private static function date(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
