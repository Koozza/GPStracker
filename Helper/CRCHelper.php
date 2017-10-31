<?php

namespace Helper;


class CRCHelper
{
    /**
     * CRC X25 Calculation for a given string
     *
     * @param string $data
     *
     * @return int|number
     */
    public static function crcx25($data)
    {
        $content = explode(' ', $data);
        $len = count($content);
        $n = 0;

        $crc = 0xFFFF;
        while ($len > 0) {
            $crc ^= hexdec($content[$n]);
            for ($i = 0; $i < 8; $i++) {
                if ($crc & 1) {
                    $crc = ($crc >> 1) ^ 0x8408;
                } else {
                    $crc >>= 1;
                }
            }
            $n++;
            $len--;
        }

        return (~$crc);
    }


    /**
     * Get CRC calculation for a given string
     *
     * @param $data
     *
     * @return string
     */
    public static function getCRC($data)
    {
        $crc = str_replace('ffff', '', dechex(self::crcx25($data)));
        $crc = strtoupper(substr($crc, 0, 2)) . ' ' . strtoupper(substr($crc, 2, 2));

        return $crc;
    }
}