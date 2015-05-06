<?xml version="1.0" encoding="utf-8"?>
<!--
    Export to dict format.

    Copyright © Goya Pty Ltd 2014, All Rights Reserved
-->

<xsl:stylesheet version="1.0"
        xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
        xmlns:restfm="http://www.restfm.com"
        exclude-result-prefixes="restfm">
    <xsl:output method="text" omit-xml-declaration="yes" indent="no" encoding="UTF-8"/>

    <xsl:template match="/restfm:resource">
        <xsl:apply-templates select="restfm:metaField"/>
        <xsl:apply-templates select="restfm:data"/>
        <xsl:apply-templates select="restfm:info"/>
    </xsl:template>

    <!--process metaField section-->
    <xsl:template match="restfm:metaField">
        <xsl:for-each select="restfm:row">
            <xsl:variable name="pos" select="position()"/>

            <xsl:call-template name="dict">
                <xsl:with-param name="dict_name" select="concat('metaField',$pos)"/>
                <xsl:with-param name="dict_value">

                    <xsl:for-each select="restfm:field">
                        <xsl:call-template name="dict">
                            <xsl:with-param name="dict_name" select="@name"/>
                            <xsl:with-param name="dict_value" select="."/>
                        </xsl:call-template>
                    </xsl:for-each>

                </xsl:with-param>
            </xsl:call-template>
        </xsl:for-each>
    </xsl:template>

    <!--process data section-->
    <xsl:template match="restfm:data">
        <xsl:for-each select="restfm:row">
            <xsl:variable name="pos" select="position()"/>

            <xsl:call-template name="dict">
                <xsl:with-param name="dict_name" select="concat('data',$pos)"/>
                <xsl:with-param name="dict_value">

                    <xsl:for-each select="restfm:field">
                        <xsl:call-template name="dict">
                            <xsl:with-param name="dict_name" select="@name"/>
                            <xsl:with-param name="dict_value" select="."/>
                        </xsl:call-template>
                    </xsl:for-each>

                </xsl:with-param>
            </xsl:call-template>
        </xsl:for-each>
    </xsl:template>

    <!--process info section-->
    <xsl:template match="restfm:info">

        <xsl:call-template name="dict">
            <xsl:with-param name="dict_name" select="'info'"/>
            <xsl:with-param name="dict_value">

                <xsl:for-each select="restfm:field">
                    <xsl:call-template name="dict">
                        <xsl:with-param name="dict_name" select="@name"/>
                        <xsl:with-param name="dict_value" select="."/>
                    </xsl:call-template>
                </xsl:for-each>
            </xsl:with-param>
        </xsl:call-template>
    </xsl:template>


    <!--templates to do the work-->


    <!--output the dict format-->
    <xsl:template name="dict">
        <xsl:param name="dict_name" />
        <xsl:param name="dict_value" />

        <xsl:text>&lt;:</xsl:text>

        <xsl:call-template name="substitute">
            <xsl:with-param name="text" select="$dict_name" />
        </xsl:call-template>

        <xsl:text>:=</xsl:text>

        <xsl:call-template name="substitute">
            <xsl:with-param name="text" select="$dict_value" />
        </xsl:call-template>

        <xsl:text>:&gt;&#xA;</xsl:text>

    </xsl:template>

    <!--replace all four control characters-->
    <xsl:template name="substitute">
        <xsl:param name="text" />

        <xsl:variable name="equals">
            <xsl:call-template name="string-replace-all">
                <xsl:with-param name="text" select="$text" />
                <xsl:with-param name="replace" select="'='" />
                <xsl:with-param name="by" select="'/='" />
            </xsl:call-template>
        </xsl:variable>

        <xsl:variable name="colon">
            <xsl:call-template name="string-replace-all">
                <xsl:with-param name="text" select="$equals" />
                <xsl:with-param name="replace" select="':'" />
                <xsl:with-param name="by" select="'/:'" />
            </xsl:call-template>
        </xsl:variable>

        <xsl:variable name="left">
            <xsl:call-template name="string-replace-all">
                <xsl:with-param name="text" select="$colon" />
                <xsl:with-param name="replace" select="'&lt;'" />
                <xsl:with-param name="by" select="'/&lt;'" />
            </xsl:call-template>
        </xsl:variable>

        <xsl:variable name="right">
            <xsl:call-template name="string-replace-all">
                <xsl:with-param name="text" select="$left" />
                <xsl:with-param name="replace" select="'&gt;'" />
                <xsl:with-param name="by" select="'/&gt;'" />
            </xsl:call-template>
        </xsl:variable>

        <xsl:value-of select="$right"/>

    </xsl:template>

    <!--XSLT version of the substitite function-->
    <xsl:template name="string-replace-all">
        <xsl:param name="text" />
        <xsl:param name="replace" />
        <xsl:param name="by" />
        <xsl:choose>
            <xsl:when test="contains($text, $replace)">
                <xsl:value-of select="substring-before($text,$replace)" />
                <xsl:value-of select="$by" />
                <xsl:call-template name="string-replace-all">
                    <xsl:with-param name="text" select="substring-after($text,$replace)" />
                    <xsl:with-param name="replace" select="$replace" />
                    <xsl:with-param name="by" select="$by" />
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$text" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

</xsl:stylesheet>
