<?php

namespace Richardds\ECBAPI;

/**
 * Class ECB
 * @package Richardds\ECBAPI
 */
class ECB
{
    /**
     * Default ECB url for daily exchange reference update
     *
     * @var string
     */
    const EXCHANGE_REFERENCE_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    /**
     * @return Currency[]
     * @throws ECBException
     */
    public static function getExchangeReferences(): array
    {
        $raw_xml_data = self::fetch(self::EXCHANGE_REFERENCE_URL);

        if (($xml = @simplexml_load_string($raw_xml_data)) !== false) {
            $exchange_references = [];
            $exchange_references['EUR'] = new Currency('EUR', 1.0000);

            foreach ($xml->Cube->Cube->Cube as $row) {
                $code = (string)($row['currency'] ?? '');
                $rate = (double)($row['rate'] ?? 0);

                if (empty($code) || strlen($code) != 3) {
                    throw new ECBException('Invalid currency code',ECBException::DATA_PARSE_FAILED);
                }

                if ($rate <= 0) {
                    throw new ECBException('Invalid currency rate',ECBException::DATA_PARSE_FAILED);
                }

                $exchange_references[$code] = new Currency($code, $rate);
            }

            return $exchange_references;
        }

        throw new ECBException('Failed to parse data from ECB', ECBException::DATA_PARSE_FAILED);
    }

    /**
     * @param string $url
     * @return string
     * @throws ECBException
     */
    private static function fetch(string $url): string
    {
        $ch = @curl_init($url . '?' . uniqid());

        if ($ch === false) {
            throw new ECBException('Failed to initialize cURL', ECBException::DATA_DOWNLOAD_FAILED);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (($data = @curl_exec($ch)) !== false) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($http_code != 200) {
                curl_close($ch);
                throw new ECBException('Invalid HTTP return code', ECBException::DATA_DOWNLOAD_FAILED);
            }

            curl_close($ch);

            return $data;
        }

        $curl_error = curl_error($ch);
        curl_close($ch);

        throw new ECBException($curl_error, ECBException::DATA_DOWNLOAD_FAILED);
    }
}
