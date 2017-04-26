<?php

use DNSRBL\DNSRBL;

class NetDNSRBLTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var DNSRBL the rbl object
     */
    private $_rbl;

    protected function setUp()
    {
        $this->_rbl = new DNSRBL(
            array(
                'dnsbl' => array(
                    'sbl-xbl.spamhaus.org',
                    'bl.spamcop.net'
                ),
                'surbl' => array()
            ),
            new Doctrine\Common\Cache\PhpFileCache('tests/cache')
        );
    }

    public function testHostsAlwaysAreListed()
    {
        $this->assertTrue($this->_rbl->isListed("127.0.0.2"));
    }

    public function testTrustworthyHostsArentListed()
    {
        $this->_rbl->setBlacklists(array('dnsbl' => array('sbl.spamhaus.org')));
        $this->assertFalse($this->_rbl->isListed("mail.nohn.net"));
        $this->assertFalse($this->_rbl->isListed("212.112.226.205"));
        $this->assertFalse($this->_rbl->isListed("smtp1.google.com"));
    }

    public function testSetters()
    {
        $this->assertTrue(
            $this->_rbl->setBlacklists(
                array('dnsbl' => array('sbl.spamhaus.org'))
            )
        );
        $this->assertEquals(
            array('dnsbl' => array('sbl.spamhaus.org'), 'surbl' => array()),
            $this->_rbl->getBlacklists()
        );
    }

    public function testGetAll()
    {
        $this->_rbl->setBlacklists(array('dnsbl' => array('sbl.spamhaus.org')));
        $this->assertTrue($this->_rbl->isListed("127.0.0.2"));

        $r = $this->_rbl->getAll("127.0.0.2");
        $this->assertArraySubset(
            array(
                'sbl.spamhaus.org' =>
                    array(
                        0 =>
                            array(
                                'host' => '2.0.0.127.sbl.spamhaus.org',
                                'class' => 'IN',
                                'type' => 'A',
                                'ip' => '127.0.0.2',
                            ),
                        1 =>
                            array(
                                'host' => '2.0.0.127.sbl.spamhaus.org',
                                'class' => 'IN',
                                'type' => 'TXT',
                                'txt' => 'https://www.spamhaus.org/sbl/query/SBL2'
                            ),
                    ),
            ),
            $r
        );
    }

    public function testGetListingBlacklists()
    {
        $this->_rbl->setBlacklists(array('dnsbl' => array('sbl.spamhaus.org')));
        $this->assertTrue($this->_rbl->isListed("127.0.0.2"));

        $r = $this->_rbl->getListingBlacklists("127.0.0.2");
        $this->assertEquals(array("sbl.spamhaus.org"), $r);

        $r = $this->_rbl->getListingBlacklists("www.google.de");
        $this->assertEquals(array(), $r);
    }

    public function testMultipleBlacklists()
    {
        $this->_rbl->setBlackLists(
            array(
                'dnsbl' => array(
                    'sbl-xbl.spamhaus.org',
                    'bl.spamcop.net'
                )
            )
        );

        $this->assertFalse($this->_rbl->isListed('212.112.226.205'));

        $r = $this->_rbl->getListingBlacklists('212.112.226.205');
        $this->assertEquals(array(), $r);
    }

    public function testIsListedMulti()
    {
        $this->_rbl->setBlackLists(
            array(
                'dnsbl' => array(
                    'sbl-xbl.spamhaus.org',
                    'bl.spamcop.net'
                )
            )
        );

        $this->assertTrue($this->_rbl->isListed('127.0.0.2'));
    }

    public function testGetListingBlacklistsMulti()
    {
        $this->_rbl->setBlackLists(
            array(
                'dnsbl' => array(
                    'sbl.spamhaus.org',
                    'bl.spamcop.net'
                )
            )
        );

        $this->assertTrue($this->_rbl->isListed('127.0.0.2'));
        $this->assertEquals(
            array(
                'sbl.spamhaus.org',
                'bl.spamcop.net'
            ),
            $this->_rbl->getListingBlacklists('127.0.0.2')
        );
    }

    public function testBogusInput()
    {
        $this->_rbl->setBlacklists(array('rbl.efnet.org'));
        $this->assertFalse($this->_rbl->isListed(null));
        $this->assertNull($this->_rbl->getAll(null));
        $this->assertFalse($this->_rbl->isListed(false));
        $this->assertNull($this->_rbl->getAll(false));
        $this->assertFalse($this->_rbl->isListed(true));
        $this->assertNull($this->_rbl->getAll(true));
    }

    public function testGetListingBlacklistsDoesNotBreakSilentlyIfHostIsListed()
    {
        $this->_rbl->setBlacklists(
            array(
                'dnsbl' => array(
                    'bl.spamcop.net',
                    'b.barracudacentral.org'
                )
            )
        );
        $ip = '127.0.0.2';
        $this->assertTrue($this->_rbl->isListed($ip));
        $this->assertEquals(
            array('bl.spamcop.net', 'b.barracudacentral.org'),
            $this->_rbl->getListingBlacklists($ip)
        );
    }

    public function testGetListingBlDoesNotBreakSilentlyIfHostIsNotListed()
    {
        $this->_rbl->setBlacklists(
            array(
                'dnsbl' => array(
                    'bl.spamcop.net',
                    'b.barracudacentral.org'
                )
            )
        );
        $ip = '127.0.0.1';
        $this->assertFalse($this->_rbl->isListed($ip));
        $this->assertEquals(array(), $this->_rbl->getListingBlacklists($ip));
        $this->assertFalse($this->_rbl->isListed($ip));
        $this->assertEquals(array(), $this->_rbl->getListingBlacklists($ip));
    }

    public function testGetAllWithSurbl()
    {

        $this->_rbl->setBlacklists(
            array(
                'surbl' => array(
                    'dbl.spamhaus.org'
                )
            )
        );
        $host = 'gmail.com';
        $this->assertFalse($this->_rbl->isListed($host));

        $host = 'dbltest.com';
        $this->assertTrue($this->_rbl->isListed($host));
        $r = $this->_rbl->getAll($host);
        $this->assertArraySubset(
            array(
                'dbl.spamhaus.org' =>
                    array(
                        0 =>
                            array(
                                'host' => 'dbltest.com.dbl.spamhaus.org',
                                'class' => 'IN',
                                'type' => 'A',
                                'ip' => '127.0.1.2',
                            ),
                        1 =>
                            array(
                                'host' => 'dbltest.com.dbl.spamhaus.org',
                                'class' => 'IN',
                                'type' => 'TXT'
                            )
                    )
            ),
            $r
        );
    }

    public function testSetBlacklistNotArray()
    {
        $this->assertFalse($this->_rbl->setBlacklists(null));
    }

    public function testFallbackArrayCache()
    {
        $rbl = new DNSRBL(
            array(
                'dnsbl' => array(
                    'bl.spamcop.net'
                ),
                'surbl' => array()
            )
        );
        $this->assertTrue($rbl->isListed('127.0.0.2'));
        $this->assertFalse($rbl->isListed('127.0.0.1'));
    }
}
