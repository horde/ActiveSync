<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class TruncationCapTest extends TestCase
{
    public function testForceDisabledLeavesClientValue()
    {
        $this->assertSame(200000, Horde_ActiveSync::forceTruncationSize(200000, 0));
        $this->assertSame(false, Horde_ActiveSync::forceTruncationSize(false, 0));
        $this->assertSame(500, Horde_ActiveSync::forceTruncationSize(500, 0));
    }

    public function testForceOverridesLargerClientBodyPreference()
    {
        $this->assertSame(5000, Horde_ActiveSync::forceTruncationSize(200000, 5000));
    }

    public function testForceOverridesSmallerClientBodyPreference()
    {
        $this->assertSame(5000, Horde_ActiveSync::forceTruncationSize(500, 5000));
    }

    public function testForceOverridesUnlimitedClientBodyPreference()
    {
        $this->assertSame(5000, Horde_ActiveSync::forceTruncationSize(0, 5000));
        $this->assertSame(5000, Horde_ActiveSync::forceTruncationSize(null, 5000));
        $this->assertSame(5000, Horde_ActiveSync::forceTruncationSize(false, 5000));
    }

    public function testGetForcedTruncationSizePrefersNewKey()
    {
        $this->assertSame(
            5000,
            Horde_ActiveSync::getForcedTruncationSize([
                'forcetruncationsize' => 5000,
                'maximumtruncationsize' => 500,
            ])
        );
    }

    public function testGetForcedTruncationSizeFallsBackToDeprecatedKey()
    {
        $this->assertSame(
            500,
            Horde_ActiveSync::getForcedTruncationSize([
                'maximumtruncationsize' => 500,
            ])
        );
    }

    public function testApplyForcedTruncationSizeToOptions()
    {
        $options = [
            'bodyprefs' => [
                Horde_ActiveSync::BODYPREF_TYPE_HTML => [
                    'type' => Horde_ActiveSync::BODYPREF_TYPE_HTML,
                    'truncationsize' => 200000,
                ],
                Horde_ActiveSync::BODYPREF_TYPE_PLAIN => [
                    'type' => Horde_ActiveSync::BODYPREF_TYPE_PLAIN,
                    'truncationsize' => 500,
                ],
            ],
            'mimetruncation' => false,
            'truncation' => 51200,
        ];

        Horde_ActiveSync::applyForcedTruncationSize($options, 0);
        $this->assertSame(200000, $options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['truncationsize']);
        $this->assertSame(500, $options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize']);
        $this->assertSame(false, $options['mimetruncation']);

        Horde_ActiveSync::applyForcedTruncationSize($options, 5000);
        $this->assertSame(5000, $options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_HTML]['truncationsize']);
        $this->assertSame(5000, $options['bodyprefs'][Horde_ActiveSync::BODYPREF_TYPE_PLAIN]['truncationsize']);
        $this->assertSame(5000, $options['mimetruncation']);
        $this->assertSame(5000, $options['truncation']);
    }
}
