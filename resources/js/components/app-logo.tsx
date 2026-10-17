import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center">
                <AppLogoIcon className="size-8 drop-shadow-[0_0_6px_rgba(251,191,36,0.35)]" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold tracking-wide">AFK Builder</span>
                <span className="text-muted-foreground truncate text-[10px] tracking-[0.2em] uppercase">Companion App</span>
            </div>
        </>
    );
}
