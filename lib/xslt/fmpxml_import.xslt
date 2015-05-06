<?xml version="1.0" encoding="utf-8"?>
<!--
    Import FileMaker FMPXMLRESULT Grammar.
    Copyright © Goya Pty Ltd 2006-2014, All Rights Reserved
-->
<xsl:stylesheet version="1.0"
        xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
        xmlns:fmp="http://www.filemaker.com/fmpxmlresult"
        exclude-result-prefixes="fmp">
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>
    <xsl:template match="fmp:FMPXMLRESULT">
        <resource xmlns="http://www.restfm.com">
            <data>
                <xsl:for-each select="fmp:RESULTSET/fmp:ROW">
                    <row>
                        <xsl:for-each select="fmp:COL">
                            <xsl:variable name="col_index" select="position()"/>
                            <field>
                                <xsl:attribute name="name">
                                    <xsl:value-of select="/fmp:FMPXMLRESULT/fmp:METADATA/fmp:FIELD[$col_index]/@NAME"/>
                                </xsl:attribute>
                                <xsl:value-of select="fmp:DATA"/>
                            </field>
                        </xsl:for-each>
                    </row>
                </xsl:for-each>
            </data>
            <meta>
                <xsl:for-each select="fmp:RESULTSET/fmp:ROW">
                    <row>
                        <field>
                            <xsl:if test="@RECORDID != ''">
                                <xsl:attribute name="recordID">
                                    <xsl:value-of select="@RECORDID"/>
                                </xsl:attribute>
                            </xsl:if>
                        </field>
                    </row>
                </xsl:for-each>
            </meta>
            <info>
                <fetchCount><xsl:value-of select="fmp:RESULTSET/@FOUND"/></fetchCount>
            </info>
        </resource>
    </xsl:template>
</xsl:stylesheet>
