<?php

namespace DNSRBL;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;

/**
 * Class DNSRBL
 *
 * @package DNSRBL
 */
class DNSRBL
{
    /**
     * Array of blacklists
     *
     * @var    array
     * @access protected
     */
    protected $blacklists = array();

    protected $cache = null;

    protected $cachePrefix = "dnsrbl";

    /**
     * Constructor
     *
     * @param array         $blacklists  example: array(
     *                                      'dnsbl' => array('sbl.spamhaus.org'),
     *                                      'surbl' => array('dbl.spamhaus.org')
     *                                   )
     * @param CacheProvider $cacheDriver doctrine cache driver
     */
    public function __construct($blacklists, CacheProvider $cacheDriver = null)
    {
        $this->setBlacklists($blacklists);
        if (isset($cacheDriver)) {
            $this->cache = $cacheDriver;
        } else {
            $this->cache = new ArrayCache();
        }
        putenv('RES_OPTIONS=retrans:1 retry:1 timeout:1 attempts:1');
    }

    /**
     * Set the blacklist to a desired blacklist.
     *
     * @param array $blacklists Array of blacklists to use.
     *
     * @access public
     * @return bool true if the operation was successful
     */
    public function setBlacklists($blacklists)
    {
        if (is_array($blacklists)) {
            $this->blacklists = $blacklists;
            if (!isset($this->blacklists['dnsbl'])) {
                $this->blacklists['dnsbl'] = array();
            }
            if (!isset($this->blacklists['surbl'])) {
                $this->blacklists['surbl'] = array();
            }
            return true;
        }
        return false;
    }

    /**
     * Get the blacklists.
     *
     * @access public
     * @return array Currently set blacklists.
     */
    public function getBlacklists()
    {
        return $this->blacklists;
    }

    /**
     * Get details
     *
     * @param string $host ip or hostname
     *
     * @return array
     */
    public function getAll($host)
    {
        if (!is_string($host)) {
            return null;
        }
        $results = array();
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (count($this->blacklists['dnsbl'])) {
                foreach ($this->blacklists['dnsbl'] as $blacklist) {
                    $records = $this->getDnsRecord($blacklist, $host);
                    $results[$blacklist] = $records;
                }
            }
        } else {
            if (count($this->blacklists['surbl'])) {
                foreach ($this->blacklists['surbl'] as $blacklist) {
                    $records = $this->getDnsRecord($blacklist, $host);
                    $results[$blacklist] = $records;
                }
            }
        }
        return $results;
    }

    /**
     * Get list of blacklists listing the host
     *
     * @param string $host ip or hostname
     *
     * @return array
     */
    public function getListingBlacklists($host)
    {
        $blacklists = array();
        $result = $this->getAll($host);
        foreach ($result as $blacklist => $records) {
            if (!empty($records)) {
                $blacklists[] = $blacklist;
            }
        }
        return $blacklists;
    }

    /**
     * Checks if the supplied Host is listed in one or more of the RBLs.
     *
     * @param string $host ip or hostname
     *
     * @access public
     * @return boolean
     */
    public function isListed($host)
    {
        if (filter_var($host, FILTER_VALIDATE_IP)
            && count($this->blacklists['dnsbl'])
        ) {
            foreach ($this->blacklists['dnsbl'] as $blacklist) {
                if (!empty($this->getDnsRecord($blacklist, $host))) {
                    return true;
                }
            }
        } elseif (count($this->blacklists['surbl'])) {
            foreach ($this->blacklists['surbl'] as $blacklist) {
                if (!empty($this->getDnsRecord($blacklist, $host))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get a DNS record
     *
     * @param string $blacklist blacklist hostname
     * @param string $host      hostname to look up
     *
     * @return array
     */
    protected function getDnsRecord($blacklist, $host)
    {
        $hostForLookup = $this->getHostForLookup($host, $blacklist);
        $records = $this->cache->fetch($this->cachePrefix . $host . $blacklist);
        if (is_array($records)) {
            return $records;
        }
        $records = dns_get_record($hostForLookup, DNS_A | DNS_TXT);
        if (!empty($records)) {
            $this->cache->save(
                $this->cachePrefix . $host . $blacklist, $records, $records[0]['ttl']
            );
        } else {
            $this->cache->save(
                $this->cachePrefix . $host . $blacklist, array(), 3600
            );
        }
        return $records;
    }

    /**
     * Get host to lookup. Lookup a host if neccessary and get the
     * complete FQDN to lookup.
     *
     * @param string $host      Host OR IP to use for building the lookup.
     * @param string $blacklist Blacklist to use for building the lookup.
     *
     * @access protected
     * @return string Ready to use host to lookup
     */
    protected function getHostForLookup($host, $blacklist)
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->reverseHost($host) . ".$blacklist.";
        }
        return $host . ".$blacklist.";
    }

    /**
     * Reverse the order of an IP. 127.0.0.1 -> 1.0.0.127. Currently
     * only works for v4-adresses
     *
     * @param string $host IP address to reverse.
     *
     * @access protected
     * @return string Reversed IP
     */
    protected function reverseHost($host)
    {
        return implode('.', array_reverse(explode('.', $host)));
    }

}
