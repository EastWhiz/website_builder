import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const permissions = auth.permissions || {};
    const roleName = user?.role?.name ?? null;
    const isSuperAdmin = Number(user?.role_id) === 1;
    const isPlatformAdmin = roleName === 'admin';
    const orgTeamAdminFlag = Boolean(permissions.org_team_admin);
    /** Org primary owner or org_admin membership (not platform super/platform admin). */
    const isOrgAdminNav = orgTeamAdminFlag && !isSuperAdmin && !isPlatformAdmin;
    const hasTeamSettingsPermission =
        Boolean(permissions.member_invite) ||
        Boolean(permissions.member_role_assign) ||
        Boolean(permissions.member_edit) ||
        Boolean(permissions.member_soft_delete) ||
        Boolean(permissions.member_restore) ||
        Boolean(permissions.member_activate_complete) ||
        Boolean(permissions.role_view);
    /** Super / platform admins have no org team; org admins and permitted members only. */
    const showTeamSettings =
        !isSuperAdmin &&
        !isPlatformAdmin &&
        (isOrgAdminNav || hasTeamSettingsPermission);
    const canManageOrgLandingPages = Boolean(permissions.can_manage_org_landing_pages);
    const landingPagesHref = canManageOrgLandingPages
        ? route('organization.landing-pages')
        : route('userThemes', { id: user.id });
    const landingPagesActive =
        route().current('userThemes') || route().current('organization.landing-pages');
    // console.log(user);

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={user.role_id == 1 ? route('dashboard') : route('memberDashboard')}
                                    active={route().current('dashboard') || route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                            </div>
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('templates')}
                                    active={route().current('templates') || route().current('addTemplate') || route().current('editTemplate')}
                                >
                                    Themes
                                </NavLink>
                            </div>
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('angles')}
                                    active={route().current('angles') || route().current('addAngle') || route().current('editAngle')}
                                >
                                    Angles
                                </NavLink>
                            </div>
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('thank-you-pages.index')}
                                    active={route().current('thank-you-pages.index') || route().current('thank-you-pages.create') || route().current('thank-you-pages.edit')}
                                >
                                    Thank You Pages
                                </NavLink>
                            </div>
                            {user && user.role_id == 1 ?
                                <>
                                    <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                        <NavLink
                                            href={route('users')}
                                            active={route().current('users') || route().current('userThemes')}
                                        >
                                            Users
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.organizations')}
                                            active={route().current('admin.organizations')}
                                        >
                                            Organizations
                                        </NavLink>
                                        <NavLink
                                            href={route('otp.services.manage')}
                                            active={route().current('otp.services.manage')}
                                        >
                                            OTP Services
                                        </NavLink>
                                        <NavLink
                                            href={route('api.categories.manage')}
                                            active={route().current('api.categories.manage')}
                                        >
                                            API Platforms
                                        </NavLink>
                                        {permissions.org_role_crud && (
                                            <NavLink
                                                href={route('admin.roles.manage')}
                                                active={route().current('admin.roles.manage')}
                                            >
                                                Roles
                                            </NavLink>
                                        )}
                                    </div>
                                </> : <>
                                    <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                        {showTeamSettings && (
                                            <NavLink
                                                href={route('organization.team.settings')}
                                                active={route().current('organization.team.settings')}
                                            >
                                                Team Settings
                                            </NavLink>
                                        )}
                                        <NavLink
                                            href={landingPagesHref}
                                            active={landingPagesActive}
                                        >
                                            Landing Pages
                                        </NavLink>
                                    </div>
                                </>
                            }


                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={user.role_id == 1 ? route('dashboard') : route('memberDashboard')}
                            active={route().current('dashboard') || route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('templates')}
                            active={route().current('templates') || route().current('addTemplate') || route().current('editTemplate')}
                        >
                            Themes
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('angles')}
                            active={route().current('angles') || route().current('addAngle') || route().current('editAngle')}
                        >
                            Angles
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('thank-you-pages.index')}
                            active={route().current('thank-you-pages.index') || route().current('thank-you-pages.create') || route().current('thank-you-pages.edit')}
                        >
                            Thank You Pages
                        </ResponsiveNavLink>
                        {user && user.role_id == 1 ?
                            <>
                                <ResponsiveNavLink
                                    href={route('users')}
                                    active={route().current('users') || route().current('userThemes')}
                                >
                                    Users
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    href={route('admin.organizations')}
                                    active={route().current('admin.organizations')}
                                >
                                    Organizations
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    href={route('otp.services.manage')}
                                    active={route().current('otp.services.manage')}
                                >
                                    OTP Services
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    href={route('api.categories.manage')}
                                    active={route().current('api.categories.manage')}
                                >
                                    API Platforms
                                </ResponsiveNavLink>
                                {permissions.org_role_crud && (
                                    <ResponsiveNavLink
                                        href={route('admin.roles.manage')}
                                        active={route().current('admin.roles.manage')}
                                    >
                                        Roles
                                    </ResponsiveNavLink>
                                )}
                            </> : <>
                                {showTeamSettings && (
                                    <ResponsiveNavLink
                                        href={route('organization.team.settings')}
                                        active={route().current('organization.team.settings')}
                                    >
                                        Team Settings
                                    </ResponsiveNavLink>
                                )}
                                <ResponsiveNavLink
                                    href={landingPagesHref}
                                    active={landingPagesActive}
                                >
                                    Landing Pages
                                </ResponsiveNavLink>
                            </>
                        }
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {/* {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )} */}

            <main>{children}</main>
        </div>
    );
}
