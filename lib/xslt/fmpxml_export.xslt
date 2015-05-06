<?xml version="1.0" encoding="utf-8"?>
<!--
    Export FileMaker FMPXMLRESULT Grammar.
    Copyright © Goya Pty Ltd 2006-2014, All Rights Reserved
-->
<xsl:stylesheet version="1.0"
        xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
        xmlns:restfm="http://www.restfm.com"
        exclude-result-prefixes="restfm">
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>
    <xsl:template match="restfm:resource">
        <FMPXMLRESULT xmlns="http://www.filemaker.com/fmpxmlresult">
            <METADATA>
                <xsl:for-each select="restfm:data/restfm:row[1]/*">
                        <FIELD EMPTYOK="YES" MAXREPEAT="1" TYPE="TEXT">
                            <xsl:attribute name="NAME">
                                <xsl:value-of select="@name"/>
                            </xsl:attribute>
                        </FIELD>
                </xsl:for-each>
            </METADATA>
            <RESULTSET>
                <xsl:attribute name="FOUND">
                    <xsl:value-of select="restfm:info/restfm:field[@name = 'fetchCount']"/>
                </xsl:attribute>
                <xsl:for-each select="restfm:data/restfm:row">
                    <xsl:variable name="row_index" select="position()"/>
                    <ROW>
                        <xsl:attribute name="RECORDID">
                            <xsl:value-of select="/restfm:resource/restfm:meta/restfm:row[$row_index]/restfm:field[@name = 'recordID']"/>
                        </xsl:attribute>
                        <xsl:for-each select="child::*">
                            <COL><DATA><xsl:value-of select="."/></DATA></COL>
                        </xsl:for-each>
                    </ROW>
                </xsl:for-each>
            </RESULTSET>
        </FMPXMLRESULT>
    </xsl:template>
</xsl:stylesheet>
