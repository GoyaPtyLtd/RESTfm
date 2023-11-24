This directory should contain:
- 'filemaker-server-{major}.{minor}.xx.yy-{arch}.deb'
- 'Assisted Install.txt'
- 'LicenseCert.fmcert'

Note: Ensure there is not more than one major.minor version of the
      filemaker-server .deb package here!

      This is ok (version 20.2 and 20.3 are different):
        filemaker-server-20.2.1.19-amd64.deb
        filemaker-server-20.3.1.31.19-amd64.deb
        (20.2 and 20.3 are different here)

      This is NOT ok (version 20.3 is common to both):
        filemaker-server-20.3.0.31-amd64.deb
        filemaker-server-20.3.1.31-amd64.deb
