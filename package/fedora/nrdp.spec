Name:    nrdp
Version: 0.20150122gitbd1b5d0
Release: 2%{?dist}
Summary: Nagios Remote Data Processor

License: NOSL and BSD
URL: https://github.com/NagiosEnterprises/nrdp    
# wget https://github.com/NagiosEnterprises/nrdp/archive/master.tar.gz
# mv master.tar.gz nrdp-VERSION.tar.gz
Source0: %{name}-%{version}.tar.gz
Source1: %{name}.conf
Source2: config.inc.php

Requires: nagios, httpd, php
BuildArch: noarch

%description
Nagios Remote Data Processor (NDRP) is a flexible data transport 
mechanism and processor for Nagios. It is designed with a simple 
and powerful architecture that allows for it to be easily extended 
and customized to fit individual users needs. It uses standard ports 
protocols (HTTP(S) and XML) and can be implemented as a replacement for NSCA.

%package client-shell
Summary: Send NRPD shell script for Nagios
%description client-shell
A shell script to send NRPD data to a Nagios server


%package client-php
Summary: Send NRPD php script for Nagios
Requires: php
%description client-php
A php script to send NRPD data to a Nagios server

%package client-python
Summary: Send NRPD python script for Nagios
Requires: python
%description client-python
A Python script to send NRPD data to a Nagios server

%prep
%setup -q -n nrdp-master

%build
# Nothing here


%install
mkdir -p %{buildroot}%{_sysconfdir}/%{name}
rm -f  server/config.inc.php 
install -m 0644 -D -p %{SOURCE2} %{buildroot}%{_sysconfdir}/%{name}/config.inc.php
mkdir -p %{buildroot}/usr/share/nrdp
cp -pr server/*  %{buildroot}/usr/share/nrdp/
ln -s ../../../etc/%{name}/config.inc.php ${RPM_BUILD_ROOT}%{_datadir}/%{name}/config.inc.php
# HTTPD bits
mkdir -p %{buildroot}%{_sysconfdir}/httpd/conf.d/
install -m 0644 -D -p %{SOURCE1} %{buildroot}%{_sysconfdir}/httpd/conf.d/nrdp.conf
#
sed -i "s|\r||g" clients/send_nrdp.php
mkdir -p %{buildroot}%{_bindir}
install -m 0755 -D -p clients/* %{buildroot}%{_bindir}/

%files
%{_datadir}/%{name}
%config(noreplace) %{_sysconfdir}/httpd/conf.d/nrdp.conf
%config(noreplace) %{_sysconfdir}/%{name}/config.inc.php
%{!?_licensedir:%global license %%doc}
%license LICENSE.TXT
%doc CHANGES.TXT INSTALL.TXT

%files client-shell
%{_bindir}/send_nrdp.sh
%{!?_licensedir:%global license %%doc}
%license LICENSE.TXT

%files client-php
%{_bindir}/send_nrdp.php
%{!?_licensedir:%global license %%doc}
%license LICENSE.TXT

%files client-python
%{_bindir}/send_nrdp.py
%{!?_licensedir:%global license %%doc}
%license LICENSE.TXT

%changelog
* Sat Nov 21 2015 Athmane Madjoudj <athmane@fedoraproject.org> 0.20150122gitbd1b5d0-2
- Use better version (pre-release)
- Include license file in the clients sub-pkg
- Add a license workaround

* Fri Nov 20 2015 Athmane Madjoudj <athmane@fedoraproject.org> 0.bd1b5d0git-1
- Initial spec file.
