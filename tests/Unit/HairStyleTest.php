<?php

namespace Tests\Unit;

use App\Services\AvatarGenerator;
use PHPUnit\Framework\TestCase;

class HairStyleTest extends TestCase
{
    public function test_hairstyle_detection(): void
    {
        $this->assertSame('ponytail', AvatarGenerator::hairStyle('포니테일로 묶고 빨간 후드티'));
        $this->assertSame('bun', AvatarGenerator::hairStyle('머리는 똥머리로 묶었어!'));
        $this->assertSame('down', AvatarGenerator::hairStyle('머리 풀고 니트에 청바지'));
        $this->assertNull(AvatarGenerator::hairStyle('검정 롱패딩에 청바지, 어그부츠'));
        $this->assertNull(AvatarGenerator::hairStyle('양갈래로 땋고 원피스')); // 기준 이미지 없는 헤어

        $this->assertTrue(AvatarGenerator::mentionsHairstyle('양갈래로 땋고 원피스'));
        $this->assertFalse(AvatarGenerator::mentionsHairstyle('검정 롱패딩에 청바지'));
    }
}
