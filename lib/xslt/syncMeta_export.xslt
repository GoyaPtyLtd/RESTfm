<?xml version="1.0" encoding="utf-8"?>
<!--
Import from custom XML format. Stockland Trust Group.

Copyright © Goya Pty Ltd 2014, All Rights Reserved.
-->
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:restfm="http://www.restfm.com"
	exclude-result-prefixes="restfm">
	<xsl:output method="text" indent="no" encoding="UTF-8"/>

	<xsl:template match="/restfm:resource">
		<xsl:text> { "fields": { </xsl:text>
		<xsl:if test="restfm:info/restfm:field[@name = 'X-RESTfm-Status'] &lt; 300">
			<xsl:for-each select="restfm:metaField/restfm:row">
				<xsl:text> "</xsl:text><xsl:value-of select="restfm:field[@name='name']"/><xsl:text>": { </xsl:text>
				<xsl:text> "global": </xsl:text><xsl:value-of select="restfm:field[@name='global']"/><xsl:text>, </xsl:text>
				<xsl:text> "maxRepeat": </xsl:text><xsl:value-of select="restfm:field[@name='maxRepeat']"/><xsl:text>, </xsl:text>
				<xsl:text> "resultType": "</xsl:text>
                            	<xsl:if test="restfm:field[@name='result'] != ''"><xsl:value-of select="restfm:field[@name='result']"/></xsl:if>
                            	<xsl:if test="restfm:field[@name='resultType'] != ''"><xsl:value-of select="restfm:field[@name='resultType']"/></xsl:if>
				<xsl:text>" } </xsl:text>
				<xsl:if test="position() != last()" ><xsl:text>, </xsl:text></xsl:if>
			</xsl:for-each>
		</xsl:if>
		<xsl:text> }, </xsl:text>
        <xsl:apply-templates select="restfm:info"/>
		<xsl:text> } </xsl:text>
	</xsl:template>

	<xsl:template match="restfm:info">
		<xsl:text>"info": { </xsl:text>
		<xsl:for-each select="child::*">
			<xsl:text>"</xsl:text><xsl:value-of select="@name"/><xsl:text>": </xsl:text>
			<xsl:text>"</xsl:text><xsl:value-of select="."/><xsl:text>"</xsl:text>
			<xsl:if test="position() != last()" ><xsl:text>, </xsl:text></xsl:if>
		</xsl:for-each>
		<xsl:text> } </xsl:text>
    </xsl:template>

</xsl:stylesheet>
